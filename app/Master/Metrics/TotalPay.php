<?php

namespace App\Master\Metrics;

use App\Models\Order;
use App\Traits\CashTrait;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * 累计成交金额.
 */
class TotalPay extends Card
{
    private $distributor_ids;

    /**
     * 卡片底部内容.
     *
     * @var string|Renderable|\Closure
     */
    protected $footer;

    public function __construct($distributor_ids=null)
    {
        parent::__construct();

        $this->distributor_ids = $distributor_ids;
    }

    /**
     * 初始化卡片.
     */
    protected function init()
    {
        parent::init();

        $this->title('累计成交金额（元）');
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
        $model = Order::query()->whereIn("distributorId", $request->input("distributor_ids"))
            ->whereNotNull("payTime")
            ->ofNotRefund();

        $total = (clone $model)->sum("totalFee");
        $express = (clone $model)->sum("expressFee");

        $this->content(($total-$express) / 100);
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

    public function parameters(): array
    {
        return [
            'distributor_ids'        => $this->distributor_ids,
        ];
    }
}
