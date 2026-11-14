<?php

namespace App\Admin\Controllers;

use App\Models\Store;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class StoreController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new Store(), function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('open_id');
            $grid->column('access_token')
                ->display('查看')
                ->expand(function () {
                    $card = new Card(null, $this->access_token);

                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('refresh_token')
                ->display('查看')
                ->expand(function () {
                    $card = new Card(null, $this->refresh_token);

                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('expires_in');
            $grid->column('refresh_token_expires_in');
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();
        
            $grid->disableEditButton();
            $grid->disableViewButton();
            $grid->disableCreateButton();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->like("open_id")->width(3);
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
        return Show::make($id, new Store(), function (Show $show) {
            $show->field('id');
            $show->field('open_id');
            $show->field('access_token');
            $show->field('refresh_token');
            $show->field('expires_in');
            $show->field('refresh_token_expires_in');
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
        return Form::make(new Store(), function (Form $form) {
            $form->display('id');
            $form->text('open_id');
            $form->text('access_token');
            $form->text('refresh_token');
            $form->text('expires_in');
            $form->text('refresh_token_expires_in');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
