<?php

namespace App\Admin\Forms;

use App\Models\Cash;
use App\Models\Master;
use App\Traits\CashTrait;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;

class AuditCash extends Form implements LazyRenderable
{
    use LazyWidget, CashTrait;

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return mixed
     */
    public function handle(array $input)
    {
        $ids = explode(",", $input['ids'] ?? null);
        if(!$ids) {
            return $this->response()->error('参数错误~');
        }

        $master_ids = [];
        // 提现通过时验证
        if($input['status'] == 1) {
            foreach ($ids as $id) {
                $cash = Cash::query()->find($id);
                // 判断提现金额小于等于可提现金额
                // if($cash->amount > $this->canCommissionExcludeCashId($cash->master_id, $cash->id)) {
                //     return $this->response()->error('提现金额不能大于可提现金额');
                // }

                $master_ids[] = $cash->master_id;
            }
        }

        if($input['status'] == 1) {
            Cash::query()
                ->whereIn('id', $ids)
                ->where("status", "<>", 1)
                ->update([
                    "pay_at" => now(),
                    "status" => $input['status'],
                    "remark" => $input['remark'] ?? null,
                ]);
        } else {
            Cash::query()
                ->whereIn('id', $ids)
                ->where("status", "<>", 1)
                ->update([
                    "status" => $input['status'],
                    "remark" => $input['remark'] ?? null,
                ]);
        }

        // 修改用户累计提现金额
        $master_ids = array_unique($master_ids);
        foreach ($master_ids as $master_id) {
            $cashed = Cash::query()
                ->where("master_id", $master_id)
                ->where("status", 1)
                ->sum("amount");
            Master::query()
                ->where("id", $master_id)
                ->update(["cash_money" => $cashed]);
        }

        return $this
				->response()
				->success('批量转账成功~')
                ->refresh();
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
}
