<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\AuditCash;
use App\Admin\Actions\Grid\MasterTotal;
use App\Models\Cash;
use App\Models\Master;
use App\Traits\CashTrait;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

class CashController extends AdminController
{
    use CashTrait;

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(Cash::with(["master"]), function (Grid $grid) {
            $grid->model()->orderBy('id', 'desc');

            $grid->column('id')->sortable();
            $grid->column('master.username');
            $grid->column('amount');
            $grid->column('status')
                ->using(Cash::$status)
                ->label([
                    0 => 'gray',
                    1 => 'success',
                    2 => 'danger',
                ]);
            $grid->column('remark');
            $grid->column('master.type')
                ->using(Master::$type)
                ->label();
            $grid->column('master.realname');
            $grid->column('master.account');
            $grid->column('master.canCommission');
            $grid->column('apply_at')->sortable();
            $grid->column('pay_at')->sortable();
            // $grid->column('created_at')->sortable();
            // $grid->column('updated_at')->sortable();

            $grid->showColumnSelector();
            // $grid->hideColumns(['master.account']);

            $grid->disableViewButton();
            $grid->disableDeleteButton();
            $grid->disableBatchDelete();

            $grid->export();

            $grid->batchActions(new AuditCash());

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                if($actions->row->status) {
                    $actions->disableEdit();
                }

                // 达人统计
                if($actions->row->master) {
                    $actions->append(new MasterTotal());
                }
                // $url = admin_url("master/".$actions->row->master_id."/chart");
                // $actions->append('<a href="'.$url.'">统计</a>');
            });
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->like("master.username")->width(2);
                $filter->equal("status")
                    ->select(Cash::$status)
                    ->width(2);
                $filter->between("apply_at")
                    ->date()
                    ->width(4);
                $filter->between("pay_at")
                    ->date()
                    ->width(4);
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
        return Show::make($id, new Cash(), function (Show $show) {
            $show->field('id');
            $show->field('master_id');
            $show->field('amount');
            $show->field('status');
            $show->field('remark');
            $show->field('apply_at');
            $show->field('pay_at');
            $show->field('created_at');
            $show->field('updated_at');

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
        $that = $this;
        return Form::make(new Cash(), function (Form $form) use ($that) {
            $form->display('id');
            $form->select('master_id')
                ->options(Master::all()->pluck("username", "id"))
                ->required();
            if($form->isEditing()) {
                $canCommission = $that->canCommissionExcludeCashId($form->model()->master_id, $form->getKey());
                $cashed = $that->cashedExcludeCashId($form->model()->master_id, $form->getKey());
                $form->html("<div style='padding-top:10px;'>￥ {$canCommission}</div>", "可提现金额");
                $form->html("<div style='padding-top:10px;'>￥ {$cashed}</div>", "已提现金额");
            }
            $form->currency('amount')
                ->symbol('￥')
                ->required();
            $form->select('status')
                ->options(Cash::$status)
                ->when(2, function (Form $form) {
                    $form->text('remark');
                })->default(0);
            $form->display('apply_at');
            $form->display('pay_at');
        
            // $form->display('created_at');
            $form->display('updated_at');

            $form->disableViewButton();
            $form->disableViewCheck();

            $form->disableDeleteButton();
        })->saving(function (Form $form) use ($that) {
            $status = (int) ($form->status ?? 0);
            $amount = str_replace(',', '', (string) $form->amount);

            if($form->isCreating()) {
                if($form->amount < 0) {
                    return $form->response()->error('提现金额不能小于或等于0');
                }

                // 只有待审核/确认占用额度；驳回不校验。
                if(in_array($status, [0, 1], true)
                    && bccomp($amount, (string) $that->canCommission($form->master_id), 2) > 0) {
                    return $form->response()->error('提现金额不能大于可提现金额');
                }

                $form->apply_at = now();
            }

            // 待审核/确认都要占用额度；编辑时排除自身再比较。
            if($form->isEditing()
                && in_array($status, [0, 1], true)
                && bccomp($amount, (string) $that->canCommissionExcludeCashId($form->master_id, $form->getKey()), 2) > 0) {
                return $form->response()->error('提现金额不能大于可提现金额');
            }

            if($status === 1) {
                $form->pay_at = now();
            } else {
                $form->pay_at = null;
            }
        })->saved(function (Form $form, $result) {
            $cash = Cash::query()->find($form->getKey());
            if($cash) {
                $cashed = Cash::query()
                    ->where("master_id", $cash->master_id)
                    ->where("status", 1)
                    ->sum("amount");
                Master::query()
                    ->where("id", $cash->master_id)
                    ->update(["cash_money" => $cashed]);
            }
        });
    }
}
