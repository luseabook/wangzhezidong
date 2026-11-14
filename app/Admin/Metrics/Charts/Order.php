<?php

namespace App\Admin\Metrics\Charts;

use App\Admin\Metrics\Concerns\MonochromeChart;
use App\Models\Order as ModelsOrder;
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
        $this->chartOption('colors', ['#1890FF', '#91CC75', '#FACC11']);
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
        $data = [];
        $category = [];
        switch ($request->get('option')) {
            case 'year':
                $model = ModelsOrder::query()
                            ->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y' ) AS `date`");
                break;
            case 'month':
                $model = ModelsOrder::query()
                            // ->where("createTime", ">=", now()->startOfYear())
                            ->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m' ) AS `date`")
                            ->limit(20);
                break;
            case 'day':
            default:
                $model = ModelsOrder::query()
                            // ->where("createTime", ">=", now()->startOfWeek())
                            ->selectRaw("COUNT(*) as total,DATE_FORMAT( createTime, '%Y-%m-%d' ) AS `date`")
                            ->limit(20);
        }

        $total = (clone $model)->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->sortBy("date");
        $ks = (clone $model)->where("sellerType", 0)
                    ->groupBy('date')
                    ->orderBy('date', 'DESC')
                    ->get()
                    ->pluck("total", "date")
                    ->toArray();
        $wechat = (clone $model)->where("sellerType", 1)
                    ->groupBy('date')
                    ->orderBy('date', 'DESC')
                    ->get()
                    ->pluck("total", "date")
                    ->toArray();
        $data = $total->pluck("total", "date")->map(function($item, $key) use ($ks, $wechat) {
            return [
                "total" => $item,
                "ks" => isset($ks[$key]) ? $ks[$key] : 0,
                "wechat" => isset($wechat[$key]) ? $wechat[$key] : 0,
            ];
        });
        $total = $data->pluck("total")->toArray();
        $ks = $data->pluck("ks")->toArray();
        $wechat = $data->pluck("wechat")->toArray();
        $category = array_keys($data->toArray());

        // 卡片内容
        $this->withContent();
        // 图表数据
        $this->withChart($total, $ks, $wechat);
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
    public function withChart(array $total, array $ks, array $wechat)
    {
        return $this->chart([
            'series' => [
                [
                    'name' => "订单总数",
                    'data' => $total,
                ],
                [
                    'name' => "快手",
                    'data' => $ks,
                ],
                [
                    'name' => "视频号",
                    'data' => $wechat,
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
        $this->chart([
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

        return $this->applyMonoChart(['#0a0a0a', '#5c5c5a', '#8a8a86']);
    }

    /**
     * 设置卡片内容.
     *
     *
     * @return $this
     */
    public function withContent()
    {
        $model = ModelsOrder::query();
        $total = (clone $model)->count();
        $ks = (clone $model)->where("sellerType", 0)->count();
        $wechat = (clone $model)->where("sellerType", 1)->count();

        return $this->content(
            <<<HTML
<div class="d-flex justify-content-start align-items-center mt-1" style="margin-bottom: 2px">
    <div class="ml-2 font-lg-1">{$total}<span class="font-sm-2 text-muted">订单总数</span></div>
    <div class="ml-2 font-lg-1">{$ks}<span class="font-sm-2 text-muted">快手</span></div>
    <div class="ml-2 font-lg-1">{$wechat}<span class="font-sm-2 text-muted">视频号</span></div>
</div>
HTML
        );
    }
}