<?php

namespace App\Admin\Controllers;

use App\Models\OrderStatistics;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

class OrderStatisticController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new OrderStatistics(), function (Grid $grid) {
            $grid->model()->orderBy('date', 'desc');
            $grid->column('id')->sortable();
            $grid->column('date');
            $grid->column('allCommission');
            $grid->column('unCommission');
            $grid->column('doneCommission');
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();

            $grid->disableActions();
            $grid->disableCreateButton();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->date("date")->width(3);
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
        return Show::make($id, new OrderStatistics(), function (Show $show) {
            $show->field('id');
            $show->field('date');
            $show->field('allCommission');
            $show->field('unCommission');
            $show->field('doneCommission');
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
        return Form::make(new OrderStatistics(), function (Form $form) {
            $form->display('id');
            $form->text('date');
            $form->text('allCommission');
            $form->text('unCommission');
            $form->text('doneCommission');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
