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
use Dcat\EasyExcel\Contracts\Sheet as SheetInterface;
use Dcat\EasyExcel\Support\SheetCollection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // excel文件路径
    private $file;
    // 订单日志ID
    private $log_id;

    /**
     * Create a new job instance.
     */
    public function __construct(string $file, int $log_id)
    {
        $this->file = $file;
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
            ->update(["status" => 4]);

        // 每1000行数据为一批数据进行读取
        $chunkSize = 1000;

        Excel::import($this->file)
            ->headings(false)
            ->first()
            ->chunk($chunkSize, function (SheetCollection $collection) {
                // 此处的数组下标依然是excel表中数据行的行号
                $rows = $collection->toArray();
                if (isset($rows[1])) unset($rows[1]);

                $time = now();
                $insert = [];
                foreach ($rows as $key => $row) {
                    $insert[] = [
                        "row" => serialize($row),
                        "order_log_id" => $this->log_id,
                        "created_at" => $time,
                        "updated_at" => $time,
                    ];
                }

                OrderLog::query()
                    ->where("id", $this->log_id)
                    ->increment("total", count($insert));

                $import = new OrderImport();
                $import->setNowTable(date('Y_m'))
                    ->insert($insert);
            });
        
        OrderLog::query()
            ->where("id", $this->log_id)
            ->update(["status" => 5]);

        // 数据处理队列
        $order_log = OrderLog::find($this->log_id);
        // 快手
        if($order_log->type == 0) {
            DealImportOrder::dispatch($this->log_id);
        } else { // 视频号
            WechatDealImportOrder::dispatchUnless($this->log_id);
        }
    }

    /**
     * 处理失败作业
     */
    public function failed(Throwable $exception): void
    {
        OrderLog::query()
            ->where("id", $this->log_id)
            ->update(["status" => 6]);
        Log::error("Job failed: " . $exception->getMessage());
    }
}
