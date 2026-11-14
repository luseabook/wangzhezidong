<?php

namespace App\Master\Forms;

use App\Models\Cash as ModelsCash;
use App\Traits\CashTrait;
use Dcat\Admin\Admin;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Contracts\LazyRenderable;

class CashEdit extends Form implements LazyRenderable
{
    use LazyWidget, CashTrait;

    // 处理请求
    public function handle(array $input)
    {
        $id = $this->payload['id'];
        $money = $input['money'] ?? 0;
        if(!$money) {
            return $this->response()->error('提现金额不能为空');
        }

        if($money <= 0) {
            return $this->response()->error('提现金额不能小于或等于0');
        }

        // 判断提现金额小于等于可提现金额
        $canCommission = $this->canCommission(Admin::user()->id);
        if($money > $canCommission) {
            return $this->response()->error('可提现金额不足，无法提现');
        }

        // 修改申请记录
        $cash = ModelsCash::query()->find($id);
        // 判断是否有权限修改
        if($cash->master_id != Admin::user()->id) {
            return $this->response()->error('无权限修改');
        }

        // 修改提现订单记录

        $cash->amount = $money;
        $cash->save();

        return $this->response()->refresh()->success('修改提现金额成功');
    }

    public function form()
    {
        $id = $this->payload['id'];
        $cash = ModelsCash::query()->find($id);

        $this->currency('money', '申请提现金额')
            ->symbol('￥')
            ->value($cash->amount)
            ->required();
    }
}