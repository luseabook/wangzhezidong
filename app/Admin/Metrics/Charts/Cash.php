<?php

namespace App\Admin\Metrics\Charts;

use App\Admin\Metrics\Concerns\MonochromeChart;
use App\Models\Cash as ModelsCash;
use Dcat\Admin\Widgets\Metrics\Line;
use Illuminate\Http\Request;

class Cash extends Line
{
    use MonochromeChart;

    /**
     * @var string
     */
    protected $name = '提现金额统计';

    /**
     * @var string
     */
    protected $label = '提现金额';

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
        $total = 0;
        $data = [];
        $category = [];
        switch ($request->get('option')) {
            case 'year':
                $model = ModelsCash::query()
                            ->where("status", 1)
                            ->selectRaw("sum(amount) as total,DATE_FORMAT( apply_at, '%Y' ) AS `date`");
                break;
            case 'month':
                $model = ModelsCash::query()
                            ->where("status", 1)
                            ->selectRaw("sum(amount) as total,DATE_FORMAT( apply_at, '%Y-%m' ) AS `date`")
                            ->limit(20);
                break;
            case 'day':
            default:
                $model = ModelsCash::query()
                            ->where("status", 1)
                            ->selectRaw("sum(amount) as total,DATE_FORMAT( apply_at, '%Y-%m-%d' ) AS `date`")
                            ->limit(20);
        }

        $model = $model->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get()
            ->sortBy("date");
            
        $data = $model->pluck("total")->toArray();
        $category = $model->pluck("date")->toArray();
        $total = ModelsCash::query()->where("status", 1)->count();

        // 卡片内容
        $this->withContent($total);
        // 图表数据
        $this->withChart($data);
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
    public function withChart(array $data)
    {
        return $this->chart([
            'series' => [
                [
                    'name' => $this->label,
                    'data' => $data,
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

        return $this->applyMonoChart(['#0a0a0a']);
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
    <h2 class="ml-1 font-large-1">{$content}</h2>
</div>
HTML
        );
    }
}