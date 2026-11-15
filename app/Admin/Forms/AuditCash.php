<?php

namespace App\Admin\Forms;

use App\Models\Cash;
use App\Services\CashAudit;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;

class AuditCash extends Form implements LazyRenderable
{
    use LazyWidget;

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return mixed
     */
    public function handle(array $input)
    {
        $ids = $this->normalizeIds($input['ids'] ?? null);
        if (empty($ids)) {
            return $this->response()->error('参数错误~');
        }

        $status = (int) ($input['status'] ?? 0);
        $remark = $input['remark'] ?? null;
        $audit = app(CashAudit::class);

        if ($status === 1) {
            $result = $audit->approve($ids);

            if (! $result['ok']) {
                if (empty($result['failures'])) {
                    return $this->response()->error('未找到可确认的提现记录');
                }

                $lines = [];
                foreach ($result['failures'] as $failure) {
                    $lines[] = "达人 {$failure['master_id']}：本次选择合计 {$failure['requested']}，当前可提现 {$failure['available']}（记录 " . implode(',', $failure['ids']) . "）";
                }

                return $this->response()->error("可提现金额不足，本次未确认任何记录：\n" . implode("\n", $lines));
            }

            $message = '批量转账成功~';
            if (! empty($result['skipped'])) {
                $message .= '（跳过 ' . count($result['skipped']) . ' 笔已确认记录）';
            }

            return $this->response()->success($message)->refresh();
        }

        $result = $audit->setStatus($ids, $status, $remark);

        $message = $status === 2 ? '批量驳回成功~' : '批量更新成功~';
        if (! empty($result['skipped'])) {
            $message .= '（跳过 ' . count($result['skipped']) . ' 笔已确认记录）';
        }

        return $this->response()->success($message)->refresh();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->select('status')
            ->options(Cash::$status)
            ->when(2, function (Form $form) {
                $form->text('remark');
            })->required();

        $this->hidden('ids')->attribute('id', 'batch-audit-id');
    }

    private function normalizeIds($ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}