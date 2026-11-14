<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendImportMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $job = DB::table("jobs")
            ->where("queue", "App\Jobs\DealImportOrder")
            ->count();
        if($job < 1) {
            $token = env("PUSHPLUS_TOKEN", "");
            if(!$token) {
                Log::error("没有配置PUSHPLUS_TOKEN");
                return;
            }

            $response = Http::post("https://www.pushplus.plus/send/", [
                "token" => $token,
                "title" => "订单导入通知",
                "content" => "订单导入处理完成，请到管理后台查看。",
                "channel" => "wechat",
                "template" => "html",
            ]);

            Log::info("发送订单导入通知: " . $response->json());
        }
    }
}
