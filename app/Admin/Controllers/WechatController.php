<?php

namespace App\Admin\Controllers;

use App\Models\Wechat;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class WechatController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new Wechat(), function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('name');
            $grid->column('appid');
            $grid->column('secret');
            $grid->column('access_token');
            $grid->column('expires_at');
            $grid->column('message')
                ->if(function() {
                    return $this->message;
                })
                ->display('查看')
                ->expand(function () {
                    $card = new Card(null, $this->message);

                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
        
                $filter->like("name")->width(2);
                $filter->like("appid")->width(2);
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
        return Show::make($id, new Wechat(), function (Show $show) {
            $show->field('id');
            $show->field('name');
            $show->field('appid');
            $show->field('secret');
            $show->field('access_token');
            $show->field('expires_in');
            $show->field('expires_at');
            $show->field('message');
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
        return Form::make(new Wechat(), function (Form $form) {
            $form->display('id');
            $form->text('name')->required();
            $form->text('appid')->required();
            $form->text('secret')->required();
            // $form->text('access_token');
            // $form->text('expires_in');
            // $form->text('message');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
