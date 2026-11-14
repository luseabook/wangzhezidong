<?php

namespace App\Admin\Controllers;

use App\Admin\Metrics\Card\CashMaster;
use App\Admin\Metrics\Card\Commission1Master;
use App\Admin\Metrics\Card\Commission2Master;
use App\Admin\Metrics\Charts\Cash;
use App\Admin\Metrics\Charts\Order;
use App\Admin\Metrics\Card\OrderMaster;
use App\Admin\Metrics\Card\RefundCommissionMaster;
use App\Admin\Metrics\Card\RefundOrderMaster;
use App\Admin\Metrics\TotalDistributor;
use App\Admin\Metrics\TotalJob;
use App\Admin\Metrics\TotalMaster;
use App\Admin\Metrics\TotalOrder;
use App\Admin\Metrics\TotalProduct;
use App\Admin\Metrics\TotalSeller;
use App\Http\Controllers\Controller;
use Dcat\Admin\Http\Controllers\Dashboard;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;

class HomeController extends Controller
{
    public function index(Content $content)
    {
        return $content
            ->header('数据面板')
            ->description('系统数据统计')
            ->body(function (Row $row) {
                $row->column(2, new TotalMaster());
                $row->column(5, new TotalDistributor());
                $row->column(5, new TotalSeller());
                $row->column(2, new TotalJob());
                $row->column(5, new TotalProduct());
                $row->column(5, new TotalOrder());

                $row->column(12, new Order());
                $row->column(12, new Cash());

                $row->column(4, new OrderMaster());
                $row->column(4, new RefundOrderMaster());
                $row->column(4, new CashMaster());
                $row->column(4, new Commission1Master());
                $row->column(4, new Commission2Master());
                $row->column(4, new RefundCommissionMaster());
            });
    }
}
