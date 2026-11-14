<?php

namespace App\Admin\Controllers;

use App\Admin\Metrics\Master\Commission;
use App\Admin\Metrics\Master\Order as MasterOrder;
use App\Admin\Metrics\Master\Total as MasterTotal;
use App\Http\Controllers\Controller;
use App\Models\Master;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;

class ChartController extends Controller
{
    public function master($id, Content $content)
    {
        return $content
            ->header('达人数据统计')
            ->description('达人数据统计')
            ->body(function (Row $row) use ($id) {
                $master = Master::query()->find($id);
                $content = "<div class='d-flex'>
                <button class='btn btn-link shadow-none' onclick='history.back();'><i class='fa fa-arrow-left'></i> 返回</button>
                <h1 class='flex-grow-1 text-center'>{$master->username}</h1>
                </div>";
                $back = Card::make($content);
                $row->column(12, $back);
            })->body(function (Row $row) use ($id) {
                $row->column(12, new MasterTotal($id));
                $row->column(12, new MasterOrder($id));
                $row->column(12, new Commission($id));
            });
    }
}
