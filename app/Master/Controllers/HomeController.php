<?php

namespace App\Master\Controllers;

use App\Master\Metrics\Examples;
use App\Http\Controllers\Controller;
use App\Master\Actions\Grid\DeleteCash;
use App\Master\Actions\Grid\EditCash;
use App\Master\Forms\Cash;
use App\Master\Metrics\AllCash;
use App\Master\Metrics\CanCash;
use App\Master\Metrics\Chart\Commission;
use App\Master\Metrics\Chart\Order as ChartOrder;
use App\Master\Metrics\DoneCash;
use App\Master\Metrics\TodayCanCash;
use App\Master\Metrics\TotalOrder;
use App\Master\Metrics\TotalPay;
use App\Master\Metrics\UnCash;
use App\Master\Metrics\ValidOrder;
use App\Models\Cash as ModelsCash;
use App\Models\ChartDistributor;
use App\Models\CountOfMaster;
use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use App\Models\Product;
use App\Traits\CashTrait;
use Dcat\Admin\Admin;
use Dcat\Admin\Http\Controllers\Dashboard;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid;

class HomeController extends Controller
{
    use CashTrait;

    public function index(Content $content)
    {
        // $master = Master::query()->with(["distributors" => function($query) {
        //     $query->where("status", 1);
        // }])->find(Admin::user()->id);
        // $distributor_ids = $master->distributors->pluck("distributorId")->toArray();

        return $content
            ->header('数据面板')
            ->description('统计数据')
            ->body(function (Row $row) {
                $row->column(3, $this->canCash());
                // $row->column(3, new TotalOrder($distributor_ids));
                // $row->column(3, new ValidOrder($distributor_ids));
                // $row->column(3, new TotalPay($distributor_ids));
                $row->column(3, new AllCash());
                $row->column(3, new UnCash());
                // $row->column(3, new TodayCanCash($distributor_ids));
                $row->column(3, new DoneCash());
                $row->column(12, $this->countOfMaster());
                $row->column(12, $this->chartDistributor());
                $row->column(12, new ChartOrder());
                // $row->column(12, new Commission());
            });
    }

    /**
     * 可提现
     */
    private function canCash()
    {
        $canCommission = $this->canCommission(Admin::user()->id);

        $modal = Modal::make()
            ->lg()
            ->title('申请提现')
            ->body(Cash::make())
            ->button('<button class="btn btn-success ml-1" style="min-width:70px;">提现</button>');

        $body = <<<HTML
        <div class="d-flex flex-wrap justify-content-between align-items-center mt-1 mr-2" style="margin-bottom: 2px; min-height:105px;">
            <h2 class="ml-1 font-lg-1" style="text-wrap: nowrap;"><i class="fa fa-rmb"></i> {$canCommission}</h2>
            {$modal}
        </div>
        HTML;

        $card = Card::make('可提现金额（元）', $body);
        // 设置间距
        $card->padding('0 15px 10px 0');
        return $card;
    }

    /**
     * 达人统计
     */
    private function countOfMaster()
    {
        $builder = CountOfMaster::query()
            ->where("master_id", Admin::user()->id);

        return Grid::make($builder, function (Grid $grid) {
            $grid->model()->orderBy('date', 'DESC');
            $grid->addTableClass('mono-master-table');
            $grid->paginate(10);

            $grid->column('id')->sortable();
            $grid->column('date', '日期');
            $grid->column('total_sale', '有效销售额')
                ->display(function ($value) {
                    return number_format($value / 100, 2);
                });
            $grid->column('total_order', '有效单数');
            $grid->column('total_commission', '预估总佣金')
                ->display(function ($value) {
                    return number_format($value / 100, 2);
                });
            $grid->column('total_uncommission', '预估剩余佣金')
                ->display(function ($value) {
                    return number_format($value / 100, 2);
                });
            $grid->column('total_cash', '已结算佣金')
                ->display(function ($value) {
                    return number_format($value / 100, 2);
                });
            $grid->column('updated_at')->sortable();

            $grid->disableViewButton();
            $grid->disableBatchActions();
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableActions();
        
            $grid->quickSearch(['id', 'date']);
        });
    }

    /**
     * 达人ID统计
     */
    private function chartDistributor()
    {
        $distributor_ids = Distributor::query()
                ->where("master_id", Admin::user()->id)
                ->where("status", 1)
                ->get()
                ->pluck("distributorId")
                ->toArray();

        $builder = ChartDistributor::query()
            ->whereIn("distributorId", $distributor_ids);

        return Grid::make($builder, function (Grid $grid) {
            $grid->addTableClass('mono-master-table');
            $grid->paginate(10);

            $grid->column('id')->sortable();
            $grid->column('distributorId', '达人ID')->copyable();
            $grid->column('total_order', '累计成交单数(已发货)');
            $grid->column('vaild_order', '累计有效单数');
            $grid->column('total_pay', '累计成交金额')
                ->display(function ($value) {
                    return number_format($value / 100, 2);
                });
            $grid->column('total_commission', '累计预估佣金')
                ->display(function ($value) {
                    return number_format($value / 100, 2);
                });
            $grid->column('today_can_cash', '今日可提现')
                ->display(function ($value) {
                    return number_format($value / 100, 2);
                });
            // $grid->column('cashed', '累计已提现')
            //     ->display(function ($value) {
            //         return number_format($value / 100, 2);
            //     });
            $grid->column('updated_at')->sortable();

            $grid->disableViewButton();
            $grid->disableBatchActions();
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableActions();
        
            $grid->quickSearch(['id', 'distributorId']);
        });
    }

    /**
     * 今日订单列表
     */
    protected function grid()
    {
        $distributor_ids = Distributor::query()
                ->where("master_id", Admin::user()->id)
                ->where("status", 1)
                ->get()
                ->pluck("distributorId")
                ->toArray();
        $builder = Order::query()
                    ->with("product")
                    ->ofCommission()
                    ->where("status", "<>", 80)
                    ->where("payTime", ">=", now()->startOfDay())
                    ->whereIn("distributorId", $distributor_ids);
        return Grid::make($builder, function (Grid $grid) {
            $grid->model()->orderBy('id', 'desc');

            $grid->column('id')->sortable();
            $grid->column('oid');
            $grid->column('payTime');
            $grid->column('sendTime');
            $grid->column('status')
                ->using(Order::$status)
                ->label("info");
            $grid->column('distributorId');
            $grid->column('distributorName');
            $grid->column('itemId');
            $grid->column('product.itemTitle');
            $grid->column('product.price')
                ->display(function($v) {
                    return $v / 100;
                });
            $grid->column('num');
            // $grid->column('totalFee')
            //     ->display(function($v) {
            //         return $v / 100;
            //     });
            $grid->column('product.commissionRate')
                ->display(function($v) {
                    return $v ? $v - 1 : 0;
                });
            $grid->column('cashPrice')
                ->display(function() {
                    return 2 * $this->commissionTotal / 100;
                });
            $grid->column('is_rate')
                ->using(Order::$isRate)
                ->label([
                    0 => "danger",
                    1 => "success",
                ]);
            $grid->column('rate1Price')
                ->display(function() {
                    return $this->commission1 / 100;
                });
            $grid->column('rate2Price')
                ->display(function() {
                    return $this->commission2 / 100;
                });
            $grid->column('cashStatus')
                ->label("cyan");

            $grid->disableViewButton();
            $grid->disableBatchActions();
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableActions();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();

                $filter->like("oid")->width(3);
                $filter->like("sellerOpenId")->width(3);
                $filter->like("distributorId")->width(3);
                $filter->like("itemId")->width(3);
                $filter->equal("status")
                    ->multipleSelect(Order::$status)
                    ->width(3);
                $filter->where("is_rate", function ($query) {
                        if($this->input) {
                            $query->whereNotIn("status", [0, 10, 80])
                                ->whereHas("product", function ($query) {
                                    $query->where('commissionRate', "<>", 0);
                                });
                        } else {
                            $query->whereIn("status", [0, 10, 80])
                                ->orWhereHas("product", function ($query) {
                                    $query->where('commissionRate', 0);
                                });
                        }
                    })
                    ->select(Order::$isRate)
                    ->width(3);
                $filter->between("payTime")->date()->width(4);
            });
        });
    }
}
