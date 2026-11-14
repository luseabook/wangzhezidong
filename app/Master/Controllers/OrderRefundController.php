<?php

namespace App\Master\Controllers;

use App\Admin\Exports\OrderExport;
use App\Models\Distributor;
use App\Models\Order;
use App\Models\Product;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class OrderRefundController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
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
                    ->ofRefund()
                    ->whereIn("distributorId", $distributor_ids);
        return Grid::make($builder, function (Grid $grid) {
            $grid->model()->orderBy("payTime", "desc");
            
            $grid->column('id')->sortable();
            $grid->column('sellerType')
                ->using(Order::$sellerType)
                ->label([
                    0 => "cyan",
                    1 => "custom"
                ]);
            $grid->column('oid');
            $grid->column('payTime');
            $grid->column('sendTime');
            $grid->column('status')
                ->using(Order::$status)
                ->label("info");
            $grid->column('refundStatus')
                ->using(Order::$refundStatus)
                ->label("custom");
            // $grid->column('sellerOpenId');
            // $grid->column('sellerNick');
            $grid->column('distributorId');
            $grid->column('distributorName')
                ->limit(3)
                ->expand(function () {
                    $card = new Card(null, $this->distributorName);
            
                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('itemId');
            $grid->column('product.itemTitle')
                ->limit(3)
                ->expand(function () {
                    $card = new Card(null, $this->product->itemTitle);
            
                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
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
                    return $this->commissionTotal / 100;
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
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();

            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableBatchActions();

            $grid->export(new OrderExport());
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->equal("sellerType")
                    ->select(Order::$sellerType)
                    ->width(3);
                $filter->like("oid")->width(3);
                $filter->like("sellerOpenId")->width(3);
                $filter->equal("distributorId")
                    ->select(Distributor::where("master_id", Admin::user()->id)->get()->pluck("distributorName", "distributorId"))
                    ->width(3);
                $filter->like("itemId")->width(3);
                $filter->equal("status")
                    ->multipleSelect(Order::$status)
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
            $show->field('id');
            $show->field('oid');
            $show->field('sellerOpenId');
            $show->field('sellerNick');
            $show->field('expressFee');
            $show->field('discountFee');
            $show->field('totalFee');
            $show->field('status');
            $show->field('cpsType');
            $show->field('payTime');
            $show->field('sendTime');
            $show->field('refundTime');
            $show->field('createTime');
            $show->field('updateTime');
            $show->field('orderDetail');
            $show->field('distributorId');
            $show->field('distributorName');
            $show->field('cpsStatus');
            $show->field('commissionRate');
            $show->field('estimatedIncome');
            $show->field('platformDpRate');
            $show->field('settlementBizType');
            $show->field('promoterServiceInCome');
            $show->field('promoterExcitationInCome');
            $show->field('orderChannel');
            $show->field('cpsDetail');
            $show->field('skuId');
            $show->field('relSkuId');
            $show->field('skuNick');
            $show->field('itemId');
            $show->field('relItemId');
            $show->field('originalPrice');
            $show->field('goodDiscountFee');
            $show->field('price');
            $show->field('cpsPayStatus');
            $show->field('created_at');
            $show->field('updated_at');
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
