<?php

namespace App\Console\Commands;

use App\Models\OrderLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ClientHealthCheck extends Command
{
    protected $signature = 'client:health-check
        {--stale-minutes=90 : 最近一次自动导入超过多少分钟视为异常}
        {--webhook= : 企微/钉钉 webhook，默认读 CLIENT_HEALTH_WEBHOOK}
        {--tenant= : 租户标识，默认读 CLIENT_HEALTH_TENANT 或 APP_URL}
        {--dry-run : 只输出，不发送 webhook}
        {--json : 以 JSON 输出}';

    protected $description = '检查客户端自动导入、失败任务和处理失败日志，异常时发 webhook';

    public function handle(): int
    {
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $tenant = (string) ($this->option('tenant') ?: getenv('CLIENT_HEALTH_TENANT') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name'));
        $webhook = (string) ($this->option('webhook') ?: getenv('CLIENT_HEALTH_WEBHOOK') ?: '');
        $issues = [];

        $latest = OrderLog::query()->where('source', 1)->latest('created_at')->first();
        if (! $latest) {
            $issues[] = $this->issue('high', 'no_auto_import', '没有找到客户端自动导入记录');
        } else {
            $lastAt = $latest->created_at ? Carbon::parse($latest->created_at) : null;
            if (! $lastAt || $lastAt->lt(now()->subMinutes($staleMinutes))) {
                $issues[] = $this->issue('high', 'stale_auto_import', '最近一次自动导入已超过 '.$staleMinutes.' 分钟', [
                    'last_log_id' => $latest->id,
                    'last_import_at' => optional($lastAt)->toIso8601String(),
                ]);
            }
        }

        try {
            $failedJobs = (int) DB::table('failed_jobs')->count();
            if ($failedJobs > 0) {
                $issues[] = $this->issue('high', 'failed_jobs', '队列存在失败任务', ['count' => $failedJobs]);
            }
        } catch (\Throwable $e) {
            $issues[] = $this->issue('warn', 'queue_check_failed', '无法读取 failed_jobs：'.$e->getMessage());
        }

        $failedImports = OrderLog::query()
            ->where('status', 11)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($failedImports > 0) {
            $issues[] = $this->issue('high', 'import_failed', '最近 24 小时存在处理失败的导入', ['count' => $failedImports]);
        }

        $pending = OrderLog::query()
            ->whereIn('status', [0, 4, 5, 9])
            ->where('created_at', '<', now()->subMinutes(max(30, $staleMinutes)))
            ->count();
        if ($pending > 0) {
            $issues[] = $this->issue('warn', 'stuck_import', '导入任务长时间未完成', ['count' => $pending]);
        }

        $payload = [
            'tenant' => $tenant,
            'app_url' => config('app.url'),
            'checked_at' => now()->toIso8601String(),
            'issues' => $issues,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->line('租户：'.$tenant);
            $this->line('最近自动导入：'.($latest?->created_at ?: '无'));
            $this->line('问题数：'.count($issues));
            foreach ($issues as $issue) {
                $this->warn("[{$issue['severity']}] {$issue['code']} - {$issue['message']}");
            }
        }

        if ($issues && $webhook && ! $this->option('dry-run')) {
            try {
                Http::timeout(5)->post($webhook, [
                    'msgtype' => 'text',
                    'text' => ['content' => $this->webhookText($payload)],
                ]);
            } catch (\Throwable $e) {
                $this->error('webhook 发送失败：'.$e->getMessage());
            }
        }

        return $issues ? 1 : 0;
    }

    private function issue(string $severity, string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
        ], $extra);
    }

    private function webhookText(array $payload): string
    {
        $lines = [
            '【客户端同步告警】'.($payload['tenant'] ?: 'unknown'),
            '地址：'.($payload['app_url'] ?: '-'),
            '时间：'.$payload['checked_at'],
        ];
        foreach ($payload['issues'] as $issue) {
            $lines[] = "[{$issue['severity']}] {$issue['message']}";
        }
        return implode("\n", $lines);
    }
}
