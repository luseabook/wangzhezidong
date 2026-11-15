<?php

namespace App\Admin\Controllers;

use App\Models\Master;
use App\Models\Money;
use App\Models\Order;
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
        return Grid::make(Money::with(['master']), function (Grid $grid) {
            $grid->model()->orderBy("id", "desc");
            
            $grid->column('id')->sortable();
            $grid->column('oid');
            $grid->column('master.username');
            $grid->column('is_parent')
                ->using(Money::$is_parent)
                ->label([
                    0 => "info",
                    1 => "cyan",
                ]);
            $grid->column('is_leader')
                ->using(Money::$is_leader)
                ->label([
                    0 => "info",
                    1 => "success",
                ]);
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

            $grid->export()->rows(function ($rows) {
                foreach ($rows as $index => &$row) {
                    $row['oid'] = (string) $row['oid'];
                    $row['before_money'] = $row['before_money'] / 100;
                    $row['after_money'] = $row['after_money'] / 100;
                    $row['money'] = $row['money'] / 100;
                    $row['type'] = isset(Money::$type[$row['type']]) ? Money::$type[$row['type']] : "未知";
                }
            
                return $rows;
            });

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->like("oid")->width(3);
                $filter->equal("master_id")
                    ->select(Master::all()->pluck("username", "id"))
                    ->width(3);
                $filter->equal("is_parent")
                    ->select(Money::$is_parent)
                    ->width(2);
                $filter->equal("is_leader")
                    ->select(Money::$is_leader)
                    ->width(2);
                $filter->equal("type")
                    ->select(Money::$type)
                    ->width(2);
                $filter->like("itemId")->width(2);
                $filter->like("content")->width(3);
            });
        });
    }
}
