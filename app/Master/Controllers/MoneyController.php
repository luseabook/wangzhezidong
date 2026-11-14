<?php

namespace App\Master\Controllers;

use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;

class MoneyController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $bulider = Money::where("master_id", Admin::user()->id)->with(['master']);
        return Grid::make($bulider, function (Grid $grid) {
            $grid->model()->orderBy("id", "desc");
            
            $grid->column('id')->sortable();
            $grid->column('oid');
            $grid->column('master.username');
            $grid->column('type')
                ->using(Money::$type)
                ->label([
                    1 => "success",
                    2 => "danger"
                ]);
            $grid->column('money')
                ->display(function ($v) {
                    return $v / 100;
                });
            $grid->column('before_money')
                ->display(function ($v) {
                    return $v / 100;
                });
            $grid->column('after_money')
                ->display(function ($v) {
                    return $v / 100;
                });
            $grid->column('content');
            $grid->column('itemId');
            $grid->column('created_at')->sortable();

            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->disableCreateButton();
            $grid->disableBatchDelete();
            $grid->disableActions();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->like("oid")->width(3);
                $filter->equal("type")
                    ->select(Money::$type)
                    ->width(2);
                $filter->like("itemId")->width(2);
                $filter->like("content")->width(3);
            });
        });
    }
}
