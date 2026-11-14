<?php

namespace App\Admin\Controllers;

use App\Models\Seller;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;

class SellerController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new Seller(), function (Grid $grid) {
            $grid->column('id')->sortable();
            // $grid->column('type')
            //     ->using(Seller::$type)
            //     ->label([
            //         0 => "cyan",
            //         1 => "custom"
            //     ]);
            $grid->column('sellerOpenId');
            $grid->column('sellerNick');
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->like("sellerOpenId")->width(3);
                $filter->like("sellerNick")->width(3);
                // $filter->equal("type")
                //     ->select(Seller::$type)
                //     ->width(2);
        
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
        return Show::make($id, new Seller(), function (Show $show) {
            $show->field('id');
            // $show->field('type')
            //     ->using(Seller::$type);
            $show->field('sellerOpenId');
            $show->field('sellerNick');
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
        return Form::make(new Seller(), function (Form $form) {
            $form->display('id');
            $id = $form->getKey();

            // $form->select('type')
            //     ->options(Seller::$type)
            //     ->default(0);
            $form->text('sellerOpenId')
                ->required()
                ->creationRules(['required', "unique:sellers"])
                ->updateRules(['required', "unique:sellers,sellerOpenId,$id"]);
            $form->text('sellerNick')
                ->required();
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
