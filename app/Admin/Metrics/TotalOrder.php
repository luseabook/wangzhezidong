<?php

namespace App\Admin\Metrics;

use App\Models\Order;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class TotalOrder extends Card
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

        $this->title('订单总数');
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
        $model = Order::query();
        $total = (clone $model)->count();
        $ks = (clone $model)->where("sellerType", 0)->count();
        $wechat = (clone $model)->where("sellerType", 1)->count();
        $content = <<<HTML
        <div class="d-flex justify-content-between mt-1" style="margin-bottom: 2px">
            <div class="ml-2 font-lg-1">{$total}<span class="font-sm-2 text-muted">总数</span></div>
            <div class="font-lg-1">{$ks}<span class="font-sm-2 text-muted">快手</span></div>
            <div class="mr-2 font-lg-1">{$wechat}<span class="font-sm-2 text-muted">视频号</span></div>
        </div>
        HTML;
        $this->content($content);
    }

    /**
     * 渲染卡片内容.
     *
     * @return string
     */
    public function renderContent()
    {
        $content = parent::renderContent();

        return $content;
    }
}
