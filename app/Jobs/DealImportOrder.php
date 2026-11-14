<?php

namespace App\Jobs;

use App\Models\Distributor;
use App\Models\Order;
use App\Models\OrderImport;
use App\Models\OrderLog;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class DealImportOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 订单日志ID
    private $log_id;
    
    /**
     * Create a new job instance.
     */
    public function __construct(int $log_id)
    {
        $this->log_id = $log_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', "1G");

        OrderLog::query()
            ->where("id", $this->log_id)
            ->update(["status" => 9]);

        $log = OrderLog::query()->find($this->log_id);
        $isFial = false;
        $import = new OrderImport();
        $import->setNowTable(date('Y_m'))
            ->where("order_log_id", $this->log_id)
            ->chunk(1000, function($items) use($log, &$isFial) {
                $success = 0;
                $fail = 0;
                $unSend = 0;
                $unPayOrCps = 0;
                foreach ($items as $item) {
                    try {
                        $row = unserialize($item->row);

                        // 付款时间为空自动去除掉未付款订单
                        // 自动去除掉没有达人ID的订单
                        if(!isset($row[2]) || !$row[2] || !isset($row[26]) || !$row[26]) {
                            $unPayOrCps += 1;
                            $item->type = 1;
                            $item->save();
                            continue;
                        }

                        // 自动去除掉未发货订单
                        // if(!isset($row[43]) || !$row[43]) {
                        //     $unSend += 1;
                        //     $item->type = 1;
                        //     $item->save();
                        //     continue;
                        // }

                        // 字段验证
                        [$check, $messages] = $this->checkExcelRow($row);

                        if($check) {
                            $isFial = true;
                            // 失败，记录失败数据
                            $fail += 1;
                            $item->type = 2;
                            $item->messages = $messages;
                            $item->save();
                            continue;
                        }

                        // 成功，记录成功数据
                        $success += 1;
                        $item->type = 3;
                        $item->save();

                        // 产品是否存在
                        // 如果不存在，则创建产品
                        if(!Product::query()->where("itemId", $row[21])->exists()) {
                            Product::query()->create([
                                "itemId" => $row[21],
                                "itemTitle" => $row[20],
                                "price" => 100 * str_replace("¥", "", $row[24]),
                                'sellerOpenId' => $log->sellerOpenId,
                                'sellerNick'   => $log->sellerNick,
                            ]);
                        }

                        $distributor = Distributor::query()->where("distributorId", $row[26])->first();

                        if(preg_match('/\b\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\b/', $row[43], $matches)) {
                            $sendTime = $matches[0];
                        } else {
                            $sendTime = null;
                        }

                        // 创建或者更新订单信息
                        Order::updateOrCreate(
                            [
                                'oid'   => trim($row[0]),
                            ],[
                                'createTime'   => Carbon::parse($row[1]),
                                'updateTime'   => Carbon::parse($row[1]),
                                'payTime'      => $row[2] ? Carbon::parse($row[2]) : null,
                                'status'       => $this->getStatus($row[4]),
                                'totalFee'     => 100 * str_replace("¥", "", $row[5]),
                                'expressFee'   => 100 * str_replace("¥", "", $row[6]),
                                'discountFee'     => 100 * (str_replace("¥", "", $row[7]) + str_replace("¥", "", $row[8]) + str_replace("¥", "", $row[9])),
                                'num' => $row[11],
                                'price' => 100 * str_replace("¥", "", $row[24]),
                                'refundStatus' => $this->getRefundStatus($row[17]),
                                'itemId' => $row[21],
                                'distributorId' => $row[26],
                                'distributorName' => $row[27] ? $row[27] : ($distributor->distributorName ??  $row[26]),
                                'estimatedIncome' => 100 * str_replace("¥", "", $row[28]),
                                'commissionRate' => str_replace("%", "", $row[29]),
                                'sendTime' => $sendTime,
                                'sellerOpenId' => $log->sellerOpenId,
                                'sellerNick'   => $log->sellerNick,
                                'orderDetail' => json_encode($row),
                                'type' => 1,
                                'importStatus' => 0,
                                'log_id' => $log->id,
                            ]
                        );
                    } catch (Exception $e) {
                        Log::error($e->getMessage());
                        Log::error(json_encode($row));
                    }
                }

                OrderLog::query()
                    ->where("id", $this->log_id)
                    ->incrementEach([
                        "success" => $success,
                        "fail" => $fail,
                        "unSend" => $unSend,
                        "unPayOrCps" => $unPayOrCps,
                    ]);
            });

        OrderLog::query()
            ->where("id", $this->log_id)
            ->update(["status" => 10]);

        // 生成失败订单Excel
        if($isFial) {
            ExportFailImportOrder::dispatch($this->log_id);
        }

        // 导入提示消息
        $log = OrderLog::query()->find($this->log_id);
        $message = "店铺：" . $log->sellerNick . " 在" . now()->format("Y-m-d H:i:s") . "导入订单完成，总共：" .$log->total . "，成功：" . $log->success . "，失败：" . $log->fail . "，未发货：" . $log->unSend . "，无效：" . $log->unPayOrCps;
        $this->sendImportMessage($message);
    }

    /**
     * 发送通知消息
     */
    private function sendImportMessage($message)
    {
        // 1分钟最多发送5次
        sleep(12);

        $token = env("PUSHPLUS_TOKEN", "");
        if(!$token) {
            Log::error("没有配置PUSHPLUS_TOKEN");
            return;
        }

        $response = Http::post("https://www.pushplus.plus/send/", [
            "token" => $token,
            "title" => "订单导入通知",
            "content" => "{$message}，具体信息请到管理后台查看。",
            "channel" => "wechat",
            "template" => "html",
        ]);

        Log::info("发送订单导入通知: " . json_encode($response->json()));
    }

    /**
     * 处理失败作业
     */
    public function failed(Throwable $exception): void
    {
        OrderLog::query()
            ->where("id", $this->log_id)
            ->update(["status" => 11]);
        Log::error("Job failed: " . $exception->getMessage());
    }

    /**
     * 检查Excel行数据
     *
     * @param array $row
     * @return array
     */
    private function checkExcelRow(array $row): array
    {
        $messages = [];
        if(!$row[0]) {
            $messages[] = "订单号 不能为空";
        }

        if(!$row[1]) {
            $messages[] = "订单创建时间 不能为空";
        }
        if(!strtotime($row[1])) {
            $messages[] = "订单创建时间 不是时间格式";
        }

        if(!$row[4]) {
            $messages[] = "订单状态 不能为空";
        }

        if(!$row[5]) {
            $messages[] = "实付款 不能为空";
        }

        if(!$row[6]) {
            $messages[] = "快递费 不能为空";
        }

        if(!$row[7]) {
            $messages[] = "店铺优惠 不能为空";
        }

        if(!$row[8]) {
            $messages[] = "平台补贴 不能为空";
        }

        if(!$row[9]) {
            $messages[] = "主播补贴 不能为空";
        }

        if(!$row[11]) {
            $messages[] = "成交数量 不能为空";
        }
        if(!is_numeric($row[11])) {
            $messages[] = "成交数量 不是数字格式";
        }
        
        if(!$row[20]) {
            $messages[] = "商品名称 不能为空";
        }

        if(!$row[21]) {
            $messages[] = "商品ID 不能为空";
        }

        if(!$row[24]) {
            $messages[] = "商品单价 不能为空";
        }

        if(!$row[26]) {
            $messages[] = "CPS达人ID 不能为空";
        }

        if(!$row[28]) {
            $messages[] = "预估推广佣金 不能为空";
        }

        if(!$row[29]) {
            $messages[] = "预估推广者分佣比例 不能为空";
        }

        return [count($messages) > 0, implode(",", $messages)];
    }

    /**
     * 获取订单状态
     *
     * @param string $status
     * @return int
     */
    private function getStatus($status): int
    {
        $map = [
            "交易关闭" => 80,
            "待付款"   => 10,
            "已付款" => 30,
            "待发货" => 30,
            "已发货" => 40,
            "已收货" => 50,
            "已签收" => 50,
            "订单成功" => 70,
            "交易成功" => 70,
            "订单失败" => 80,
        ];

        return isset($map[$status]) ? $map[$status] : 0;
    }

    /**
     * 获取订单退款状态
     *
     * @param string $status
     * @return int
     */
    private function getRefundStatus($status): int
    {
        $map = [
            "买家仅退款申请" => 10,
            "买家退货退款申请"   => 11,
            "平台介入-买家仅退款申请" => 20,
            "平台介入-买家退货退款申请" => 21,
            "平台介入-已确认退货退款" => 22,
            "商品回寄信息待买家更新" => 30,
            "商品回寄信息待卖家确认" => 40,
            "待买家确认收货" => 45,
            "退款执行中" => 50,
            "退款成功" => 60,
            "退款失败" => 70,
            "退款关闭" => 70,
            "待卖家收货" => 40,
            "待买家处理" => 80,
            "待买家退货" => 85,
            "待卖家处理" => 90,
            "待平台判定" => 25,
        ];

        return ($status && isset($map[$status])) ? $map[$status] : 0;
    }
}
