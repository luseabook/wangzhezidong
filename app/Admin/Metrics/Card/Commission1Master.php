<?php

namespace App\Admin\Metrics\Card;

use App\Models\ChartMaster;
use DateInterval;
use DatePeriod;
use DateTime;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Commission1Master extends Card
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

        $this->title('第一次结算排行');

        $this->dropdown($this->getDate());
    }

    private function getDate()
    {
        $date = [];
        $period = new DatePeriod(
            new DateTime(now()->subHours(admin_setting("commission1_days", 72))->subDays(10)->format("Y-m-d")),
            new DateInterval('P1D'),
            new DateTime(now()->subHours(admin_setting("commission1_days", 72))->format("Y-m-d"))
       );
       foreach ($period as $key => $value) {
            $t = $value->format('Y-m-d');
            $date[$t] = $t;  
        }

        return array_reverse($date);
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
        $date = $request->get('option') ?? now()->subDay()->format("Y-m-d");
        $lists = ChartMaster::query()
            ->with("master")
            ->where("date", $date)
            ->where("commission1", ">", 0)
            ->orderBy("commission1", "DESC")
            ->limit(20)
            ->get();

        $this->withContent($lists);
    }

    public function withContent($lists)
    {
        $html = "";
        if($lists->isEmpty()){
            $html = "暂无数据~";
        } else {
            foreach($lists as $key => $list) {
                $html .= "<li class='list-group-item d-flex justify-content-between align-items-center'>".($key+1).". {$list->master->username}<span class='badge badge-primary badge-pill'>{$list->commission1}</span></li>";
            }
        }
        return $this->content(
            <<<HTML
<div class="p-2">
    <ul class="list-group list-group-flush">
    {$html}
    </ul>
</div>
HTML
        );
    }
}
