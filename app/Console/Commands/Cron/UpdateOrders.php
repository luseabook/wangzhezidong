<?php

namespace App\Console\Commands\Cron;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:updateOrders {date? : Y-m-d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新订单列表';

    /**
     * 快手接口请求URL
     */
    private $baseUrl = "https://openapi.kwaixiaodian.com";

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("开始任务-更新订单列表");
        $date = $this->argument('date') ?? date("Y-m-d");

        // 获取授权小店列表
        $stores = Store::query()->get();
        foreach($stores as $store) {
            $this->info("小店开始，" . $store->open_id);

            // 获取access_token
            $access_token = $this->getAccessToken($store);
            if(!$access_token) continue;

            // 更新订单列表数据
            $this->updateOrders($access_token, $date);

            $this->info("小店结束，" . $store->open_id);
        }

        $this->info("结束任务-更新订单列表");
        return Command::SUCCESS;
    }

    /**
     * Access Token
     *
     * @param [model] $store
     * @return string|boolen
     */
    private function getAccessToken($store)
    {
        if($store->expires_in < now()->addHours(2)) {
            $response = $this->refreshAccessToken($store->refresh_token);
            if($response["result"] == 1) {
                Store::query()
                    ->where("id", $store->id)
                    ->update([
                        "access_token" => $response['access_token'],
                        "refresh_token" => $response['refresh_token'],
                        "expires_in" => Carbon::now()->addDays(2),
                        "refresh_token_expires_in" => Carbon::now()->addDays(180),
                    ]);
                
                return $response['access_token'];
            } else {
                $this->error($response["error_msg"]);
                $this->error("refreshAccessToken失败");
                return false;
            }
        }

        return $store->access_token;
    }

    /**
     * Refresh Access Token
     *
     * @param string $refresh_token
     * @return array
     */
    private function refreshAccessToken(string $refresh_token)
    {
        $query = [
            'app_id' => env("KS_APP_ID"),
            'app_secret' => env("KS_APP_SECRET"),
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
        ];

        $url = "https://openapi.kwaixiaodian.com/oauth2/refresh_token";
        
        $response = Http::asForm()->post($url, $query);

        return $this->resource($response);
    }

    /**
     * 更新订单列表数据
     *
     * @param [string] $access_token
     * @param [string] $date
     * @param string $cursor
     * @return void
     */
    private function updateOrders($access_token, $date, $cursor="")
    {
        $orders = $this->getOrders($access_token, $date, $cursor);
        if($orders["result"] == 1) {
            $this->info("订单数量 " . count($orders["data"]["orderList"]));
            // 订单列表
            foreach ($orders["data"]["orderList"] as $order) {
                $this->info("订单ID " . $order["orderBaseInfo"]["oid"]);
                Order::query()->updateOrCreate([
                    "oid" => $order["orderBaseInfo"]["oid"],
                ], [
                    "sellerOpenId" => $order["orderBaseInfo"]["sellerOpenId"],
                    "sellerNick" => $order["orderBaseInfo"]["sellerNick"],
                    "expressFee" => $order["orderBaseInfo"]["expressFee"],
                    "discountFee" => $order["orderBaseInfo"]["discountFee"],
                    "totalFee" => $order["orderBaseInfo"]["totalFee"],
                    "status" => $order["orderBaseInfo"]["status"],
                    "cpsType" => $order["orderBaseInfo"]["cpsType"],
                    "payTime" => $order["orderBaseInfo"]["payTime"] ? date("Y-m-d H:i:s", intval($order["orderBaseInfo"]["payTime"] / 1000)) : null,
                    "sendTime" => $order["orderBaseInfo"]["sendTime"] ? date("Y-m-d H:i:s", intval($order["orderBaseInfo"]["sendTime"] / 1000)) : null,
                    "refundTime" => $order["orderBaseInfo"]["refundTime"] ? date("Y-m-d H:i:s", intval($order["orderBaseInfo"]["refundTime"] / 1000)) : null,
                    "createTime" => $order["orderBaseInfo"]["createTime"] ? date("Y-m-d H:i:s", intval($order["orderBaseInfo"]["createTime"] / 1000)) : null,
                    "updateTime" => $order["orderBaseInfo"]["updateTime"] ? date("Y-m-d H:i:s", intval($order["orderBaseInfo"]["updateTime"] / 1000)) : null,
                    "orderDetail" => json_encode($order),
                    "skuId" => $order["orderItemInfo"]["skuId"],
                    "relSkuId" => $order["orderItemInfo"]["relSkuId"],
                    "skuNick" => $order["orderItemInfo"]["skuNick"],
                    "itemId" => $order["orderItemInfo"]["itemId"],
                    "relItemId" => $order["orderItemInfo"]["relItemId"],
                    "originalPrice" => $order["orderItemInfo"]["originalPrice"],
                    "goodDiscountFee" => $order["orderItemInfo"]["discountFee"],
                    "price" => $order["orderItemInfo"]["price"],
                    "num" => $order["orderItemInfo"]["num"],
                    "refundStatus" => (isset($order["orderRefundList"]) && isset($order["orderRefundList"]["refundStatus"])) ? $order["orderRefundList"]["refundStatus"] : 0,
                ]);

                // 添加商品信息
                Product::query()->updateOrCreate([
                    "itemId" => $order["orderItemInfo"]["itemId"],
                ],[
                    "skuId" => $order["orderItemInfo"]["skuId"],
                    "relSkuId" => $order["orderItemInfo"]["relSkuId"],
                    "skuDesc" => $order["orderItemInfo"]["skuDesc"],
                    "skuNick" => $order["orderItemInfo"]["skuNick"],
                    "relItemId" => $order["orderItemInfo"]["relItemId"],
                    "itemTitle" => $order["orderItemInfo"]["itemTitle"],
                    "itemLinkUrl" => $order["orderItemInfo"]["itemLinkUrl"],
                    "itemPicUrl" => $order["orderItemInfo"]["itemPicUrl"],
                    "price" => $order["orderItemInfo"]["price"],
                    "itemType" => $order["orderItemInfo"]["itemType"],
                    "sellerOpenId" => $order["orderBaseInfo"]["sellerOpenId"],
                    "sellerNick" => $order["orderBaseInfo"]["sellerNick"],
                ]);

                // 判断是否支付，未支付没有分销订单详情
                if(!$order["orderBaseInfo"]["payTime"]) continue;

                // 分销订单详情
                $cpsDetail = $this->getOrderCpsDatail($access_token, $order);
                if($cpsDetail["result"] == 1) {
                    Order::query()
                        ->where("oid", $order["orderBaseInfo"]["oid"])
                        ->update([
                            "distributorId" => $cpsDetail["data"]["distributorId"],
                            "distributorName" => $cpsDetail["data"]["distributorName"],
                            "cpsStatus" => $cpsDetail["data"]["status"],
                            "commissionRate" => $cpsDetail["data"]["commissionRate"],
                            "estimatedIncome" => $cpsDetail["data"]["estimatedIncome"],
                            "platformDpRate" => $cpsDetail["data"]["platformDpRate"],
                            "settlementBizType" => $cpsDetail["data"]["settlementBizType"],
                            "promoterServiceInCome" => isset($cpsDetail["data"]["promoterServiceInCome"]) ? $cpsDetail["data"]["promoterServiceInCome"] : 0,
                            "promoterExcitationInCome" => isset($cpsDetail["data"]["promoterExcitationInCome"]) ? $cpsDetail["data"]["promoterExcitationInCome"] : 0,
                            "orderChannel" => $cpsDetail["data"]["orderChannel"],
                            "cpsDetail" => json_encode($cpsDetail["data"]),
                        ]);
                } else {
                    $this->error("分销订单详情");
                    $this->error($order["orderBaseInfo"]["oid"]);
                    $this->error($cpsDetail["result"] . " : " . $cpsDetail["error_msg"]);
                    // $this->error(json_encode($cpsDetail));
                }
            }

            // 有下一页内容
            if($orders['data']['cursor'] !== 'nomore') {
                $this->updateOrders($access_token, $date, $orders['data']['cursor']);
            }
        } else {
            $this->error("订单列表");
            $this->error($date . " " . $cursor);
            $this->error($orders["result"] . " : " . $orders["error_msg"]);
            // $this->error(json_encode($orders));
        }
    }

    /**
     * 获取订单列表
     *
     * @param [string] $access_token
     * @param [string] $date
     * @param [string] $cursor
     * @return array
     */
    private function getOrders($access_token, $date, $cursor="")
    {
        $url = $this->baseUrl . "/open/order/cursor/list";
        $method = "open.order.cursor.list";
        $param = [
            "orderViewStatus" => 1,
            "pageSize" => 50,
            "beginTime" => Carbon::parse($date)->startOfDay()->getPreciseTimestamp(3),
            "endTime" => Carbon::parse($date)->endOfDay()->getPreciseTimestamp(3),
            "cpsType" => 2,
            "cursor" => $cursor
        ];

        $query = $this->getQuery($access_token, $method, $param);
        $response = Http::retry(3, 100)->get($url . "?" . $query);

        return $this->resource($response);
    }

    /**
     * 获取分销订单详情
     *
     * @param [string] $access_token
     * @param [array] $order
     * @return array
     */
    private function getOrderCpsDatail($access_token, $order)
    {
        $url = $this->baseUrl . "/open/seller/order/cps/detail";
        $method = "open.seller.order.cps.detail";
        $param = [
            "orderId" => $order["orderBaseInfo"]["oid"],
        ];

        $query = $this->getQuery($access_token, $method, $param);
        $response = Http::retry(3, 100)->get($url . "?" . $query);

        return $this->resource($response);
    }

    /**
     * 请求参数封装
     *
     * @param [string] $access_token
     * @param [string] $method
     * @param [array] $param
     * @return array
     */
    private function getQuery($access_token, $method, $param)
    {
        $query = [
            "appkey" => env("KS_APP_ID"),
            "timestamp" => Carbon::now()->getPreciseTimestamp(3),
            "access_token" => $access_token,
            "version" => 1,
            "method" => $method,
            "signMethod" => "MD5",
            "param" => $param,
        ];
        ksort($query);
        $query["sign"] = $this->getSign($query);
        $query["param"] = urlencode(json_encode($query["param"]));

        $str = [];
        foreach ($query as $k => $v) {
            $str[] = $k . "=" . (is_array($v) ? json_encode($v) : $v);
        }
        return implode("&", $str);
    }

    /**
     * 生成sign
     *
     * @param [array] $query
     * @return string
     */
    private function getSign($query)
    {
        ksort($query);
        $str = [];
        foreach ($query as $k => $v) {
            $str[] = $k . "=" . (is_array($v) ? json_encode($v) : $v);
        }
        $str = implode("&", $str);
        $str = $str . "&signSecret=".env("KS_SIGN_SECRET");
        return md5($str);
    }

    /**
     * 请求结果处理
     *
     * @param $response
     * @return array
     */
    private function resource($response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }
}
