<?php

namespace App\Admin\Metrics\Master;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order as ModelsOrder;
use Dcat\Admin\Widgets\Metrics\Line;
use Illuminate\Http\Request;

class Order extends Line
{
    /**
     * @var string
     */
    protected $name = '订单统计';

    protected $master_id = null;

    /**
     * 图表默认高度.
     *
     * @var int
     */
    protected $chartHeight = 350;

    public function __construct($master_id = null) 
    {
        parent::__construct();

        $this->master_id = $master_id;
    }

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
        $master = Master::query()->find($request->input("master_id"));

        $distributor_ids = Distributor::query()
                ->where("master_id", $master->id)
                ->where("status", 1)
                ->get()
                ->pluck("distributorId")
                ->toArray();
        $model = ModelsOrder::query()
                    ->whereIn("distributorId", $distributor_ids);

        $order = (clone $model)->ofCommission();
        $refund = (clone $model)->ofRefund(); 

        switch ($request->get('option')) {
            case 'year':

                $order = $order->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y' ) AS `date`");
                $refund = $refund->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y' ) AS `date`");
                break;
            case 'month':
                $order = $order->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m' ) AS `date`")
                            ->limit(20);
                $refund = $refund->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m' ) AS `date`")
                            ->limit(20);
                break;
            case 'day':
            default:
                $order = $order->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m-%d' ) AS `date`")
                            ->limit(20);
                $refund = $refund->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m-%d' ) AS `date`")
                            ->limit(20);
        }

        $order = $order->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->sortBy("date");
        $refund = $refund->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->sortBy("date");

        $orderData = $order->pluck("total")->toArray();
        $refundData = $refund->pluck("total")->toArray();
        $category = $order->pluck("date")->toArray();

        // 图表数据
        $this->withChart($orderData, $refundData);
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
    public function withChart(array $order, array $refund)
    {
        return $this->chart([
            'series' => [
                [
                    'name' => "有效订单数",
                    'data' => $order,
                ],
                [
                    'name' => "售后订单数",
                    'data' => $refund,
                ],
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
        return $this->chart([
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
    }

    public function parameters(): array
    {
        return [
            'master_id'        => $this->master_id,
        ];
    }
}