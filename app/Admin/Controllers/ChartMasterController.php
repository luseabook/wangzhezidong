<?php

namespace App\Admin\Controllers;

use App\Models\ChartMaster;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

class ChartMasterController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $builder = ChartMaster::with("master")
            ->orderBy("date", "desc");
        return Grid::make($builder, function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('date');
            $grid->column('master.username');
            $grid->column('order');
            $grid->column('refund_order');
            $grid->column('commission1')->display(fn($v) => $this->commission1_yuan);
            $grid->column('commission2')->display(fn($v) => $this->commission2_yuan);
            $grid->column('refund_commission')->display(fn($v) => $this->refund_commission_yuan);
            $grid->column('cash')->display(fn($v) => $this->cash_yuan);
            $grid->column('created_at')->sortable();
            // $grid->column('updated_at')->sortable();

            $grid->disableActions();
            $grid->disableBatchActions();
            $grid->disableCreateButton();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->equal('date')->date()->width(2);
                $filter->like("master.username")->width(2);
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
        return Show::make($id, new ChartMaster(), function (Show $show) {
            $money = fn($value) => number_format(((int) $value) / 100, 2, '.', '');
            $cash = fn($value) => number_format((float) $value, 2, '.', '');

            $show->field('id');
            $show->field('master_id');
            $show->field('date');
            $show->field('order');
            $show->field('refund_order');
            $show->field('commission1')->as($money);
            $show->field('commission2')->as($money);
            $show->field('refund_commission')->as($money);
            $show->field('cash')->as($cash);
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
        return Form::make(new ChartMaster(), function (Form $form) {
            $form->display('id');
            $form->text('master_id');
            $form->text('date');
            $form->text('order');
            $form->text('refund_order');
            $form->text('commission1');
            $form->text('commission2');
            $form->text('refund_commission');
            $form->text('cash');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
