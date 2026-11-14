<?php

namespace App\Master\Metrics\Chart;

use App\Models\Distributor;
use App\Models\Order as ModelsOrder;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Line;
use Illuminate\Http\Request;

class Commission extends Line
{
    /**
     * @var string
     */
    protected $name = '结算统计';

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
                    ->whereIn("distributorId", $distributor_ids)
                    ->ofCommission();

        $commission1Total = (clone $model)->sum('commission1') / 100;   
        $commission2Total = (clone $model)->sum('commission2') / 100;   

        switch ($request->get('option')) {
            case 'year':
                $commission1 = (clone $model)->selectRaw("SUM(commission1)/100 as total,DATE_FORMAT( commission1Time, '%Y' ) AS `date`");
                $commission2 = (clone $model)->selectRaw("SUM(commission2)/100 as total,DATE_FORMAT( commission2Time, '%Y' ) AS `date`");
                break;
            case 'month':
                $commission1 = (clone $model)->selectRaw("SUM(commission1)/100 as total,DATE_FORMAT( commission1Time, '%Y-%m' ) AS `date`");
                $commission2 = (clone $model)->selectRaw("SUM(commission2)/100 as total,DATE_FORMAT( commission2Time, '%Y-%m' ) AS `date`");
                break;
            case 'day':
            default:
                $commission1 = (clone $model)->selectRaw("SUM(commission1)/100 as total,DATE_FORMAT( commission1Time, '%Y-%m-%d' ) AS `date`");
                $commission2 = (clone $model)->selectRaw("SUM(commission2)/100 as total,DATE_FORMAT( commission2Time, '%Y-%m-%d' ) AS `date`");
        }

        $commission1 = $commission1->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->sortBy("date");
        $commission2 = $commission2->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get()
                ->sortBy("date");

        $commission1Data = $commission1->pluck("total")->toArray();
        $commission2Data = $commission2->pluck("total")->toArray();
        $category = $commission1->pluck("date")->toArray();

        // 卡片内容
        $this->withContent("第一次结算：{$commission1Total} | 第二次结算：{$commission2Total}");
        // 图表数据
        $this->withChart($commission1Data, $commission2Data);
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
    public function withChart(array $first, array $sencond)
    {
        return $this->chart([
            'series' => [
                [
                    'name' => "第一次结算",
                    'data' => $first,
                ],
                [
                    'name' => "第二次结算",
                    'data' => $sencond,
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