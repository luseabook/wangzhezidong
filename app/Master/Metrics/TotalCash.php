<?php

namespace App\Master\Metrics;

use App\Traits\CashTrait;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * 累计提现金额.
 */
class TotalCash extends Card
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

        $this->title('预估总佣金（元）');
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
        $this->content($this->allCommission(Admin::user()->id));
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
