<?php

namespace App\Master\Metrics;

use App\Models\Order;
use App\Traits\CashTrait;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * 今日可提现.
 */
class TodayCanCash extends Card
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

        $this->title('今日可提现（元）');
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
            ->ofCommission()
            ->where("commission1Time", ">=", now()->startOfDay())
            ->where("commission1Time", "<=", now()->endOfDay());

        $commission1 = (clone $model)->sum("commission1");
        $commission2 = (clone $model)->sum("commission2");

        $this->content(($commission1+$commission2) / 100);
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
