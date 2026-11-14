<?php

namespace App\Master\Forms;

use App\Models\Cash as ModelsCash;
use App\Traits\CashTrait;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Str;

class Cash extends Form
{
    use CashTrait;

    // 处理请求
    public function handle(array $input)
    {
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

        // 添加申请记录
        ModelsCash::query()->updateOrCreate([
            "master_id" => Admin::user()->id,
            "amount" => $money,
            "status" => 0,
            "apply_at" => now(),
            "uuid" => $input['uuid'],
        ]);

        return $this->response()->refresh()->success('申请提现成功');
    }

    public function form()
    {
        $canCommission = $this->canCommission(Admin::user()->id);
        $this->currency('money', '申请提现金额')
            ->symbol('￥')
            ->default($canCommission > 0 ? $canCommission : 0)
            ->required();
        $this->hidden("uuid")->value(Str::uuid()); 
    }
}