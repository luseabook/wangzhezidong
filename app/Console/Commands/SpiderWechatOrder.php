<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\Order;
use App\Models\Product;
use App\Models\Wechat;
use App\Models\WechatLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpiderWechatOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:spider-wechat-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '请求视频号小店的订单';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("开始请求视频号小店的订单");

        $dates = $this->getDates();

        foreach ($dates as $date) {
            $this->info($date);

            Wechat::chunk(10, function ($wechats) use ($date) {
            
                foreach ($wechats as $wechat) {
                    $this->info("视频小店名称: " . $wechat->name);
    
                    $has_more = true;
                    $next_key = null;
                    while($has_more) {
                        // 1. 请求 access_token
                        $access_token = $this->getAccessToken($wechat);
                        if(!$access_token) {
                            $has_more = false;
                            continue;
                        }
    
                        // 2. 请求订单列表
                        $lists = $this->getOrderListForCreate($date, $access_token, $next_key, $wechat);
                        if(!$lists) {
                            $has_more = false;
                            continue;
                        }

                        // Log::info("订单列表 ", $lists);

                        if($lists['has_more']) {
                            $has_more = true;
                            $next_key = $lists['next_key'];
                        } else {
                            $has_more = false;
                            $next_key = null;
                        }
    
                        foreach ($lists['order_id_list'] as $order_id) {
                            // 3. 请求订单详情
                            $access_token = $this->getAccessToken($wechat);
                            if(!$access_token) {
                                continue;
                            }
    
                            $this->getOrderDetail($access_token, $order_id, $wechat);
                        }
                    }

                    $has_more = true;
                    $next_key = null;
                    while($has_more) {
                        // 1. 请求 access_token
                        $access_token = $this->getAccessToken($wechat);
                        if(!$access_token) {
                            $has_more = false;
                            continue;
                        }
    
                        // 2. 请求订单列表
                        $lists = $this->getOrderListForUpdate($date, $access_token, $next_key, $wechat);
                        if(!$lists) {
                            $has_more = false;
                            continue;
                        }

                        // Log::info("订单列表 ", $lists);

                        if($lists['has_more']) {
                            $has_more = true;
                            $next_key = $lists['next_key'];
                        } else {
                            $has_more = false;
                            $next_key = null;
                        }
    
                        foreach ($lists['order_id_list'] as $order_id) {
                            // 3. 请求订单详情
                            $access_token = $this->getAccessToken($wechat);
                            if(!$access_token) {
                                continue;
                            }
    
                            $this->getOrderDetail($access_token, $order_id, $wechat);
                        }
                    }
                }
            });
        }

        $this->info("请求完成");
    }

    /**
     * 根据设置的时间获取订单请求的日期
     *
     * @return array
     */
    private function getDates()
    {
        $dates = [];  
        $start = new \DateTime(now()->subDays(admin_setting('wechat_days', 7))->format("Y-m-d"));  
        $end = new \DateTime(now()->format("Y-m-d"));  
        $end->modify('+1 day'); // 包含结束日期  
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);  
      
        foreach ($period as $date) {  
            $dates[] = $date->format('Y-m-d');  
        }
      
        return $dates; 
    }

    /**
     * 获取 access_token
     *
     * @param model $wechat
     * @return string|false
     */
    private function getAccessToken(&$wechat)
    {
        try {
            if(!$wechat->access_token || (time() > strtotime($wechat->expires_at))) {
                $response = Http::get("https://api.weixin.qq.com/cgi-bin/token", [
                    "grant_type" => "client_credential",
                    "appid" => $wechat->appid,
                    "secret" => $wechat->secret
                ]);
    
                $data = $response->json();
    
                if(isset($data['errcode']) && $data['errcode']) {
                    $wechat->message = json_encode($data);
                    $wechat->save();
    
                    return false;
                }
    
                $wechat->access_token = $data['access_token'];
                $wechat->expires_at = date("Y-m-d H:i:s", time() + $data['expires_in']);
                $wechat->save();
    
                return $data['access_token'];
            }
    
            return $wechat->access_token;
        } catch (Exception $e) {
            $wechat->message = $e->getMessage();
            $wechat->save();

            return false;
        }
    }

    /**
     * 获取订单列表
     * 按照创建时间
     *
     * @param string $access_token
     * @param string|null $next_key
     * @param model $wechat
     * @return json|false
     */
    private function getOrderListForCreate($date, $access_token, $next_key, $wechat)
    {
        try {
            $start = Carbon::parse($date)->startOfDay()->getTimeStamp();
            $end = Carbon::parse($date)->endOfDay()->getTimeStamp();
    
            $params = [
                "create_time_range" => [
                    "start_time" => $start,
                    "end_time" => $end,
                ],
                // "update_time_range" => [
                //     "start_time" => $start,
                //     "end_time" => $end,
                // ],
                "page_size" => (int)admin_setting('wechat_page_size', 10)
            ];
            if($next_key) {
                $params['next_key'] = $next_key;
            }
    
            $response = Http::post("https://api.weixin.qq.com/channels/ec/order/list/get?access_token=".$access_token, $params);
            
            $data = $response->json();
    
            if(isset($data['errcode']) && $data['errcode']) {
                $this->addLog(1, $wechat, $data);
                return false;
            }
    
            return $data;
        } catch (Exception $e) {
            $this->addLog(1, $wechat, ["errcode" => 500, "errmsg" => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获取订单列表
     * 按照更新时间
     *
     * @param string $access_token
     * @param string|null $next_key
     * @param model $wechat
     * @return json|false
     */
    private function getOrderListForUpdate($date, $access_token, $next_key, $wechat)
    {
        try {
            $start = Carbon::parse($date)->startOfDay()->getTimeStamp();
            $end = Carbon::parse($date)->endOfDay()->getTimeStamp();
    
            $params = [
                "update_time_range" => [
                    "start_time" => $start,
                    "end_time" => $end,
                ],
                "page_size" => (int)admin_setting('wechat_page_size', 10)
            ];
            if($next_key) {
                $params['next_key'] = $next_key;
            }
    
            $response = Http::post("https://api.weixin.qq.com/channels/ec/order/list/get?access_token=".$access_token, $params);
            
            $data = $response->json();
    
            if(isset($data['errcode']) && $data['errcode']) {
                $this->addLog(1, $wechat, $data);
                return false;
            }
    
            return $data;
        } catch (Exception $e) {
            $this->addLog(1, $wechat, ["errcode" => 500, "errmsg" => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获取订单详情
     *
     * @param string $access_token
     * @param string $order_id
     * @param model $wechat
     * @return void
     */
    private function getOrderDetail($access_token, $order_id, $wechat)
    {
        try {
            $response = Http::post("https://api.weixin.qq.com/channels/ec/order/get?access_token=".$access_token, [
                "order_id" => $order_id
            ]);
    
            $data = $response->json();
            if(isset($data['errcode']) && $data['errcode']) {
                $this->addLog(2, $wechat, $data);
                return;
            }
        } catch (Exception $e) {
            $this->addLog(2, $wechat, ["errcode" => 500, "errmsg" => $e->getMessage()]);
            return;
        }

        // Log::info("订单详情 ", $data);

        // 过滤不符合条件的订单
        if(in_array($data['order']['status'], [10, 250])) {
            return;
        }

        // 过滤没有分佣信息的订单
        if(!isset($data['order']['order_detail']['commission_infos']) || count($data['order']['order_detail']['commission_infos']) < 1) {
            return;
        }

        // 分佣信息
        $commissions = [];
        foreach($data['order']['order_detail']['commission_infos'] as $commission) {
            $commissions[$commission['sku_id']] = $commission;
        }

        $product_count = count($data['order']['order_detail']['product_infos']);
        foreach ($data['order']['order_detail']['product_infos'] as $product) {
            if(!isset($commissions[$product['sku_id']])) {
                continue;
            }

            // 产品是否存在
            // 如果不存在，则创建产品
            if(!Product::query()->where("type", 1)
                    ->where("itemId", $product['product_id'])
                    ->where("skuId", $product['sku_id'])
                    ->exists()) {
                Product::query()->create([
                    "type" => 1,
                    "skuId" => $product['sku_id'],
                    "itemId" => $product['product_id'],
                    "itemTitle" => $product['title'],
                    "price" => $product['sale_price'],
                    "itemPicUrl" => $product['thumb_img'],
                    'sellerOpenId' => $wechat->appid,
                    'sellerNick'   => $wechat->name,
                ]);
            }

            // $distributor = Distributor::query()->where("distributorId", $commissions[$product['sku_id']]['finder_id'])->first();

            $order = $data['order'];
            $order_detail = $order['order_detail'];

            // 创建或者更新订单信息
            Order::updateOrCreate(
                [
                    'oid'   => trim($order['order_id']),
                    'itemId' => $product['product_id'],
                    'skuId' => $product['sku_id'],
                ],[
                    'sellerType'   => 1,
                    'createTime'   => date("Y-m-d H:i:s", $order['create_time']),
                    'updateTime'   => date("Y-m-d H:i:s", $order['update_time']),
                    'payTime'      => isset($order_detail['pay_info']['pay_time']) ? date("Y-m-d H:i:s", $order_detail['pay_info']['pay_time']) : null,
                    'status'       => $this->getStatus($order['status']),
                    'totalFee'     => isset($order_detail['price_info']['order_price']) ? intval($order_detail['price_info']['order_price'] / $product_count) : 0,
                    'expressFee'   => isset($order_detail['price_info']['freight']) ? intval($order_detail['price_info']['freight'] / $product_count) : 0,
                    'discountFee'  => isset($order_detail['price_info']['discounted_price']) ? intval($order_detail['price_info']['discounted_price'] / $product_count) : 0,
                    'num' => $product['sku_cnt'],
                    'price' => $product['sale_price'],
                    'refundStatus' => 0,
                    'distributorId' => $commissions[$product['sku_id']]['finder_id'],
                    'distributorName' => $commissions[$product['sku_id']]['nickname'],
                    'estimatedIncome' => $commissions[$product['sku_id']]['amount'],
                    'commissionRate' => 0,
                    'sendTime' => (isset($order_detail['delivery_info']['ship_done_time']) && $order_detail['delivery_info']['ship_done_time']) ? date("Y-m-d H:i:s", $order_detail['delivery_info']['ship_done_time']) : null,
                    'sellerOpenId' => $wechat->appid,
                    'sellerNick'   => $wechat->name,
                    'orderDetail' => json_encode($data),
                    'type' => 0,
                    'importStatus' => 0,
                ]
            );


            // 有售后订单
            if(isset($order['aftersale_detail']['aftersale_order_list']) && count($order['aftersale_detail']['aftersale_order_list']) > 0) {
                $this->getRefundOrder($order['aftersale_detail']['aftersale_order_list'], $access_token, $wechat);
            }
        }

    }

    /**
     * 获取售后订单
     *
     * @param array $lists
     * @param string $access_token
     * @param model $wechat
     * @return void
     */
    private function getRefundOrder($lists, $access_token, $wechat)
    {
        foreach ($lists as $list) {
            try {
                $response = Http::post("https://api.weixin.qq.com/channels/ec/aftersale/getaftersaleorder?access_token=".$access_token, [
                    "after_sale_order_id" => $list['aftersale_order_id']
                ]);
        
                $data = $response->json();
                if(isset($data['errcode']) && $data['errcode']) {
                    $this->addLog(3, $wechat, $data);
                    continue;
                }
            } catch (Exception $e) {
                $this->addLog(3, $wechat, ["errcode" => 500, "errmsg" => $e->getMessage()]);
                continue;
            }

            // 正常订单
            $status = [
                "USER_CANCELD",
                "MERCHANT_REJECT_REFUND",
                "MERCHANT_REJECT_RETURN",
                "RETURN_CLOSED",
                "PLATFORM_REFUND_FAIL",
                "MERCHANT_REFUND_RETRY_FAIL",
                "MERCHANT_FAIL",
            ];

            $refundStatus = [
                "MERCHANT_PROCESSING",
                "USER WAIT_RETURN",
                "MERCHANT_WAIT_RECEIPT",
                "MERCHANT_OVERDUE_REFUND",
                "MERCHANT_REFUND_SUCCESS",
                "MERCHANT_RETURN_SUCCESS",
                "PLATFORM_REFUNDING",
                "USER_WAIT_CONFIRM",
                "USER_WAIT_CONFIRM UPDATE",
                "USER_WAIT_HANDLE_MERCHANT_AFTER_SALE",
            ];
            if(in_array($data['after_sale_order']['status'], $status)) {
                Order::query()->where("oid", $data['after_sale_order']['order_id'])
                    ->where("itemId", $data['after_sale_order']['product_info']['product_id'])
                    ->where("skuId", $data['after_sale_order']['product_info']['sku_id'])
                    ->update([
                        'refundStatus' => 0,
                        'importStatus' => 0,
                        'refundDetail' => json_encode($data),
                    ]);
                continue;
            } else if(in_array($data['after_sale_order']['status'], $refundStatus)) {
                Order::query()->where("oid", $data['after_sale_order']['order_id'])
                    ->where("itemId", $data['after_sale_order']['product_info']['product_id'])
                    ->where("skuId", $data['after_sale_order']['product_info']['sku_id'])
                    ->update([
                        'refundStatus' => $this->getRefundStatus($data['after_sale_order']['status']),
                        'importStatus' => 0,
                        'refundDetail' => json_encode($data),
                    ]);
                continue;
            }
        }
    }

    /**
     *  售后状态
     *
     * @param strings $status
     * @return int
     */
    private function getRefundStatus($status)
    {
        $map = [
            "MERCHANT_PROCESSING"  => 5,
            "USER_WAIT_RETURN"  => 11,
            "MERCHANT_WAIT_RECEIPT"  => 40,
            "MERCHANT_OVERDUE_REFUND"  => 41,
            "MERCHANT_REFUND_SUCCESS"  => 60,
            "MERCHANT_RETURN_SUCCESS"  => 60,
            "PLATFORM_REFUNDING"  => 20,
            "USER_WAIT_CONFIRM"  => 45,
            "USER_WAIT_CONFIRM_UPDATE"  => 51,
            "USER_WAIT_HANDLE_MERCHANT_AFTER_SALE"  => 52,
        ];

        return $map[$status];
    }

    /**
     * 记录订单请求日志
     *
     * @param int $type 1列表 2详情 3售后
     * @param model $wechat
     * @param json $response
     * @return void
     */
    private function addLog($type, $wechat, $response)
    {
        try {
            WechatLog::query()
            ->create([
                "type" => $type,
                "wechat" => json_encode($wechat),
                "response" => json_encode($response),
            ]);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
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
            10 => 10,
            20 => 30,
            21 => 30,
            30 => 40,
            100 => 70,
            200 => 80,
            250 => 80,
        ];

        return isset($map[$status]) ? $map[$status] : 0;
    }
}
