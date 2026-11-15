<?php

namespace App\Master\Controllers;

use App\Admin\Exports\OrderExport;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Product;
use App\Traits\CashTrait;
use App\Traits\OrderGridColumns;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

class OrderController extends AdminController
{
    use CashTrait;
    use OrderGridColumns;

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $that = $this;
        $distributor_ids = Distributor::query()
                ->where("master_id", Admin::user()->id)
                // ->where("status", 1)
                ->get()
                ->pluck("distributorId")
                ->toArray();
        $builder = Order::query()
                    ->with("product")
                    // ->ofCommission()
                    ->where(function($q) use ($distributor_ids) {
                        $q->whereIn("distributorId", $distributor_ids)
                            ->orWhere("leader_master_id", Admin::user()->id);
                    });
        return Grid::make($builder, function (Grid $grid) use ($builder, $that) {
            $grid->model()->orderBy("payTime", "desc");

            $this->orderGridColumns($grid, false);

            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableCreateButton();
            $grid->disableBatchActions();

            $grid->export(new OrderExport());

            $grid->header(function ($collection) use ($grid, $builder, $that) {
                $query = $builder;
            
                // 拿到表格筛选 where 条件数组进行遍历
                $grid->model()->getQueries()->unique()->each(function ($value) use (&$query) {
                    if (in_array($value['method'], ['paginate', 'get', 'orderBy', 'orderByDesc'], true)) {
                        return;
                    }
            
                    $query = call_user_func_array([$query, $value['method']], $value['arguments'] ?? []);
                });
            
                // 查出统计数据
                // 预估总佣金
                $allCommission = $that->allCommissionByModel((clone $query));
                $doneCommission = $that->doneCommissionByModel((clone $query));
                $unCommission = bcsub($allCommission, $doneCommission, 2);

                $body = <<<HTML
                <div class="card mt-2">
                    <div class="card-body">
                    <span class="text-danger">预估总佣金：{$allCommission}</span>
                    <span class="text-success">预估剩余佣金：{$unCommission}</span>
                    <span class="text-info">已结算佣金：{$doneCommission}</span>
                    </div>
                </div>
                HTML;

                // 自定义组件
                return $body;
            });
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->equal("sellerType")
                    ->select(Order::$sellerType)
                    ->width(3);
                $filter->like("oid")->width(3);
                $filter->like("sellerOpenId")->width(3);
                $filter->equal("distributorId")
                    ->select(Distributor::where("master_id", Admin::user()->id)->get()->pluck("distributorId", "distributorId"))
                    ->width(3);
                $filter->like("distributorName")->width(3);
                $filter->like("itemId")->width(3);
                $filter->equal("status")
                    ->multipleSelect(Order::$status)
                    ->width(3);
                $filter->equal("refundStatus")
                    ->select(Order::$refundStatus)
                    ->width(3);
                $filter->equal("cpsPayStatus")
                    ->select(Order::$cpsPayStatus)
                    ->width(3);
                $filter->where("is_refund", function ($query) {
                        if($this->input) {
                            $query->ofRefund();
                        } else {
                            $query->whereIn("refundStatus", [0, 70]);
                        }
                    })
                    ->select(Order::$isRefund)
                    ->width(3);
                $filter->myCustomBetween("payTime")->date()->width(4);
                $filter->myCustomBetween("sendTime")->date()->width(4);
            });
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        return Show::make($id, new Order(), function (Show $show) {
            $money = function ($value) {
                return number_format(((int) $value) / 100, 2, '.', '');
            };

            $show->field('id');
            $show->field('oid');
            $show->field('sellerOpenId');
            $show->field('sellerNick');
            $show->field('expressFee')->as($money);
            $show->field('discountFee')->as($money);
            $show->field('totalFee')->as($money);
            $show->field('status')->using(Order::$status);
            $show->field('refundStatus')->using(Order::$refundStatus);
            $show->field('cpsType');
            $show->field('payTime');
            $show->field('sendTime');
            $show->field('refundTime');
            $show->field('createTime');
            $show->field('updateTime');
            $show->field('orderDetail');
            $show->field('distributorId');
            $show->field('distributorName');
            $show->field('cpsStatus')->using(Order::$cpsStatus);
            $show->field('commissionRate');
            $show->field('estimatedIncome')->as($money);
            $show->field('platformDpRate');
            $show->field('settlementBizType');
            $show->field('promoterServiceInCome')->as($money);
            $show->field('promoterExcitationInCome')->as($money);
            $show->field('orderChannel');
            $show->field('cpsDetail');
            $show->field('skuId');
            $show->field('relSkuId');
            $show->field('skuNick');
            $show->field('itemId');
            $show->field('relItemId');
            $show->field('originalPrice')->as($money);
            $show->field('goodDiscountFee')->as($money);
            $show->field('price')->as($money);
            $show->field('cpsPayStatus')->using(Order::$cpsPayStatus);
            $show->field('rate');
            $show->field('is_rate')->using(Order::$isRate);
            $show->field('commissionTotal')->as($money);
            $show->field('commission_rate');
            $show->field('commission1')->as($money);
            $show->field('commission1_rate');
            $show->field('commission2')->as($money);
            $show->field('commission2_rate');
            $show->field('created_at');
            $show->field('updated_at');

            $show->disableEditButton();
            $show->disableDeleteButton();
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new Order(), function (Form $form) {
            $form->display('id');
            $form->text('oid');
            $form->text('sellerOpenId');
            $form->text('sellerNick');
            $form->text('expressFee');
            $form->text('discountFee');
            $form->text('totalFee');
            $form->text('status');
            $form->text('cpsType');
            $form->text('payTime');
            $form->text('sendTime');
            $form->text('refundTime');
            $form->text('createTime');
            $form->text('updateTime');
            $form->text('orderDetail');
            $form->text('distributorId');
            $form->text('distributorName');
            $form->text('cpsStatus');
            $form->text('commissionRate');
            $form->text('estimatedIncome');
            $form->text('platformDpRate');
            $form->text('settlementBizType');
            $form->text('promoterServiceInCome');
            $form->text('promoterExcitationInCome');
            $form->text('orderChannel');
            $form->text('cpsDetail');
            $form->text('skuId');
            $form->text('relSkuId');
            $form->text('skuNick');
            $form->text('itemId');
            $form->text('relItemId');
            $form->text('originalPrice');
            $form->text('goodDiscountFee');
            $form->text('price');
            $form->text('cpsPayStatus');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
