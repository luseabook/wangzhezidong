<?php

namespace App\Admin\Controllers;

use App\Models\OrderHistory;
use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;

/**
 * 订单变更记录（只读）
 *
 * 数据来自 OrderObserver 的写入：订单任何被跟踪字段发生变化（自动同步 / 手工编辑 / 系统任务）
 * 都会在这里留一条记录，可按订单号、字段、来源、时间筛选。
 */
class OrderHistoryController extends AdminController
{
    protected function grid()
    {
        return Grid::make(new OrderHistory(), function (Grid $grid) {
            $grid->model()->orderByDesc('id');

            $grid->column('id', 'ID')->sortable();
            $grid->column('oid', '订单号');
            $grid->column('field', '字段')->using(OrderHistory::FIELD_LABEL);
            $grid->column('old_value', '原值');
            $grid->column('new_value', '新值');
            $grid->column('source', '来源')
                ->using(OrderHistory::SOURCE)
                ->label([0 => 'primary', 1 => 'success', 2 => 'default']);
            $grid->column('operator', '操作人');
            $grid->column('order_log_id', '导入批次');
            $grid->column('changed_at', '变更时间')->sortable();

            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->disableRowSelector();
            $grid->disableBatchActions();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('oid', '订单号');
                $filter->equal('order_id', '订单ID');
                $filter->equal('field', '字段')->select(OrderHistory::FIELD_LABEL);
                $filter->equal('source', '来源')->select(OrderHistory::SOURCE);
                $filter->between('changed_at', '变更时间')->datetime();
            });

            $grid->export()->rows(function ($rows) {
                foreach ($rows as $row) {
                    $row->field = $row->fieldLabel();
                    $row->source = $row->sourceLabel();
                }
                return $rows;
            });
        });
    }
}