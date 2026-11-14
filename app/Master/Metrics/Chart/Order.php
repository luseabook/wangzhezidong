<?php

namespace App\Master\Metrics\Chart;

use App\Admin\Metrics\Concerns\MonochromeChart;
use App\Models\Distributor;
use App\Models\Order as ModelsOrder;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Line;
use Illuminate\Http\Request;

class Order extends Line
{
    use MonochromeChart;

    /**
     * @var string
     */
    protected $name = '订单统计';

    /**
     * 图表默认高度.
     *
     * @var int
     */
    protected $chartHeight = 350;

    /**
     * 初始化卡片内容
     *
     * @return void
     */
    protected function init()
    {
        parent::init();

        $this->title($this->name);
        $this->dropdown([
            'day' => '日',
            'month' => '月',
            'year' => '年',
        ]);
        $this->chartStraight();
    }

    /**
     * 处理请求
     *
     * @param Request $request
     *
     * @return mixed|void
     */
    public function handle(Request $request)
    {
        $distributor_ids = Distributor::query()
                ->where("master_id", Admin::user()->id)
                ->where("status", 1)
                ->get()
                ->pluck("distributorId")
                ->toArray();
        $model = ModelsOrder::query()
                    ->whereIn("distributorId", $distributor_ids);

        $order = (clone $model)->ofCommission();
        // $refund = $model->ofRefund();
        // $orderTotal = (clone $order)->count();   
        // $refundTotal = (clone $refund)->count();   

        switch ($request->get('option')) {
            case 'year':
                $order = $order->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y' ) AS `date`");
                // $refund = $refund->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y' ) AS `date`");
                break;
            case 'month':
                $order = $order->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m' ) AS `date`")
                            ->limit(20);
                // $refund = $refund->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m' ) AS `date`")
                //             ->limit(20);
                break;
            case 'day':
            default:
                $order = $order->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m-%d' ) AS `date`")
                            ->limit(20);
                // $refund = $refund->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m-%d' ) AS `date`")
                //             ->limit(20);
        }

        $order = $order->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->sortBy("date");
        // $refund = $refund->groupBy('date')
        //         ->orderBy('date', 'DESC')
        //         ->get()
        //         ->sortBy("date");

        $orderData = $order->pluck("total")->toArray();
        // $refundData = $refund->pluck("total")->toArray();
        $category = $order->pluck("date")->toArray();

        // 卡片内容
        // $this->withContent("订单总数：{$orderTotal} | 售后总数：{$refundTotal}");
        // 图表数据
        $this->withChart($orderData);
        // 图表类别
        $this->withCategories($category);
    }

    /**
     * 设置图表数据.
     *
     * @param array $data
     *
     * @return $this
     */
    public function withChart(array $order, array $refund=null)
    {
        return $this->chart([
            'series' => [
                [
                    'name' => "订单数",
                    'data' => $order,
                ],
                // [
                //     'name' => "售后数",
                //     'data' => $refund,
                // ],
            ],
        ]);
    }

    /**
     * 设置图表类别.
     *
     * @param array $data
     *
     * @return $this
     */
    public function withCategories(array $data)
    {
        $this->chart([
            'colors' => ["#68a67d", "#833500"],
            'xaxis' => [
                'labels' => [
                    'show' => true,
                ],
                'categories' => $data,
            ],
            'chart' => [
                'type' => 'area',
                'grid' => [
                    'show' => true,
                ],
            ],
            'markers' => [
                "size" => 5,
                "strokeWidth" => 2,
                "strokeOpacity" => 0.9,
                "fillOpacity" => 1,
                "shape" => "circle",
                "radius" => 2,
            ],
        ]);

        return $this->applyMonoChart(['#0a0a0a', '#8a8a86']);
    }

    /**
     * 设置卡片内容.
     *
     * @param string $content
     *
     * @return $this
     */
    public function withContent($content)
    {
        return $this->content(
            <<<HTML
<div class="d-flex justify-content-between align-items-center mt-1" style="margin-bottom: 2px">
    <h5 class="ml-1 font-large-1">{$content}</h5>
</div>
HTML
        );
    }
}