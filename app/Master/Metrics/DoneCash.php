<?php

namespace App\Master\Metrics;

use App\Models\Cash as ModelsCash;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * 累计已提现金额
 */
class DoneCash extends Card
{
    /**
     * 卡片底部内容.
     *
     * @var string|Renderable|\Closure
     */
    protected $footer;

    /**
     * 初始化卡片.
     */
    protected function init()
    {
        parent::init();

        $this->title('累计已提现（元）');
    }

    /**
     * 处理请求.
     *
     * @return void
     */
    public function handle(Request $request)
    {
        // “累计已提现”只统计审核通过的提现，待审核金额仍由可提现卡片预留。
        $total = ModelsCash::query()
            ->where('master_id', Admin::user()->id)
            ->where('status', 1)
            ->sum('amount');

        $this->content(bcadd($total, 0, 2));
    }

    /**
     * 渲染卡片内容.
     *
     * @return string
     */
    public function renderContent()
    {
        $content = parent::renderContent();

        return <<<HTML
<div class="d-flex justify-content-between align-items-center mt-1 mr-2" style="margin-bottom: 2px">
    <h2 class="ml-1 font-lg-1"><i class="fa fa-rmb"></i> {$content}</h2>
</div>
HTML;
    }
}
