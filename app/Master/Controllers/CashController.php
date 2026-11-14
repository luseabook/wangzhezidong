<?php

namespace App\Master\Controllers;

use App\Master\Actions\Grid\DeleteCash;
use App\Master\Actions\Grid\EditCash;
use App\Models\Cash;
use App\Models\Master;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

class CashController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $builder = Cash::query()
                    ->where("master_id", Admin::user()->id)
                    ->with(["master"]);

        return Grid::make($builder, function (Grid $grid) {
            $grid->column('id')->sortable();
            // $grid->column('master_id');
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
            $grid->column('apply_at')->sortable();
            $grid->column('pay_at')->sortable();
            // $grid->column('created_at')->sortable();
            // $grid->column('updated_at')->sortable();

            $grid->showColumnSelector();
            // $grid->hideColumns(['master.account']);

            // $grid->actions(function (Grid\Displayers\Actions $actions) {
            //     if(!$actions->row->status) {
            //         $actions->append(new EditCash());
            //         $actions->append(new DeleteCash());
            //     }
            // });

            $grid->disableViewButton();
            $grid->disableBatchActions();
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableBatchDelete();
            $grid->disableActions();

            $grid->export();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->panel();

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
            $show->disableEditButton();
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new Cash(), function (Form $form) {
            $form->display('id');
            $form->text('master_id');
            $form->text('amount');
            $form->text('status');
            $form->text('remark');
            $form->text('apply_at');
            $form->text('pay_at');
        
            // $form->display('created_at');
            $form->display('updated_at');

            $form->disableEditingCheck();
            $form->disableDeleteButton();
            $form->disableViewButton();
            $form->disableSubmitButton();
        });
    }
}
