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

class WechatExportFailImportOrder implements ShouldQueue
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
                "下单时间",
                "订单状态",
                "发货方式",
                "收件人姓名",
                "收件人地址",
                "省",	
                "市",	
                "区",	
                "收件人手机",	
                "买家备注",	
                "商家备注",	
                "订单实际支付金额",
                "订单运费",	
                "支付方式",	
                "支付时间",	
                "交易单号",	
                "物流公司",	
                "快递单号",	
                "带货人视频号",
                "授权号",	
                "预估佣金",	
                "实际佣金",	
                "实际服务费",	
                "技术服务费",	
                "技术服务费（将以人气卡形式返还）",	
                "商品spu",	
                "商品编码",	
                "商品名称",	
                "商品属性",	
                "商品价格",	
                "商品数量",	
                "商品发货",	
                "商品售后",	
                "商品已退款金额",
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
