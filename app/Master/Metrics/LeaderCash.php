<?php

namespace App\Master\Metrics;

use App\Traits\CashTrait;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * 团长分佣金额（元）。
 * 与「累计预估佣金」分开显示，避免达人误以为可提现比预估佣金多。
 */
class LeaderCash extends Card
{
    use CashTrait;

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

        $this->title('团长分佣（元）');
    }

    /**
     * 处理请求.
     *
     * @param Request $request
     *
     * @return void
     */
    public function handle(Request $request)
    {
        $this->content($this->leaderCommissioned(Admin::user()->id));
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