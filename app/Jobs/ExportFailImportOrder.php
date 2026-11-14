<?php

namespace App\Jobs;

use App\Models\OrderImport;
use App\Models\OrderLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Dcat\EasyExcel\Excel;
use Illuminate\Support\Facades\Log;

class ExportFailImportOrder implements ShouldQueue
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
        $log = OrderLog::query()->find($this->log_id);

        $titles = [
                "导入失败信息",
                "订单号",
                "订单创建时间",
                "订单支付时间",
                "预售定金支付时间",
                "订单状态",
                "实付款",
                "快递费",
                "店铺优惠",
                "平台补贴",
                "主播补贴",
                "渠道",
                "支付方式",
                "成交数量",
                "买家留言",
                "账号类型",
                "账号明细",
                "订单备注",
                "旗帜颜色",
                "退货退款",
                "活动订单",
                "预售/承诺发货时间",
                "商品名称",
                "商品ID",
                "商品规格",
                "SKU编码",
                "商品单价",
                "CPS达人ID",
                "CPS达人昵称",
                "预估推广佣金",
                "预估推广者分佣比例",
                "团长ID",
                "团长昵称",
                "快赚客ID",
                "快赚客昵称",
                "授权推广者ID",
                "授权推广者昵称",
                "收货人姓名",
                "收货人电话",
                "收货地址-省",
                "收货地址-市",
                "收货地址-区",
                "收货地址-街道",
                "收货地址",
                "发货时间",
                "快递公司",
                "快递单号",
                "物流信息",
                "实名姓名",
                "身份证号",
                "门店ID",
                "门店名称",
                "门店地址",
        ];
        $import = new OrderImport();
        $import = $import->setNowTable($log->created_at->format("Y_m"))
            ->where("type", 2)
            ->where("order_log_id", $log->id);

        $path = public_path("storage/export/");
        $file = $log->sellerOpenId.'_'.$log->sellerNick.'_'.$log->created_at->format("Y-m-d-H-i-s").'.xlsx';
        @mkdir($path, 0777, true);
        
        $lastId = 0;
        Excel::export()
            ->headings($titles) // 设置标题
            ->chunk(function () use (&$lastId, $import) {
                // 每次获取1000条数据导入
                $chunkSize = 1000;

                // 这里使用forPageAfterId批量读取数据，也可以用forPage
                $collection = $import->forPageAfterId($chunkSize, $lastId)->get();

                // 暂存最后一个ID
                $last = $collection->last();
                if($last) {
                    $lastId = $last->id;
                }

                // 当数据库查不到值时会停止执行闭包内的逻辑
                return $collection->map(function($item) {
                    return array_merge([$item->messages], unserialize($item->row));
                });
            })
            ->store($path . $file);

        $log->failFile = $file;
        $log->save();
    }
}
