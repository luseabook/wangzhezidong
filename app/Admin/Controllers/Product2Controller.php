<?php

namespace App\Admin\Controllers;

use App\Models\Master;
use App\Models\Product;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class Product2Controller extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(Product::with(['master']), function (Grid $grid) {
            $grid->model()
                ->where('master_id', '<>', 0)
                ->orderBy("id", "DESC");

            $grid->column('id')->sortable();
            $grid->column('type')
                ->using(Product::$type)
                ->label([
                    0 => "cyan",
                    1 => "custom"
                ]);
            $grid->column('itemId');
            $grid->column('skuId');
            $grid->column('itemTitle');
            $grid->column('itemLinkUrl')
                ->display('查看')
                ->expand(function () {
                    $a = "<a href='{$this->itemLinkUrl}' target='_blank'>{$this->itemLinkUrl}</a>";
                    $card = new Card(null, $a);
                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('itemPicUrl')->image("", 50, 50);
            $grid->column('price')
                ->display(function($v){ 
                    return $v / 100;
                });
            $grid->column('itemType')
                ->using(Product::$itemType);
            // $grid->column('status')
            //     ->using(Product::$status)
            //     ->label([
            //         0 => "danger",
            //         1 => "success",
            //     ]);
            $grid->column('sellerOpenId');
            $grid->column('sellerNick');
            $grid->column('commissionRate')->editable();
            $grid->column('master.username', '团长');
            $grid->column('leader_rate')->editable();
            $grid->column('created_at')->sortable();
            $grid->column('updated_at')->sortable();

            $grid->hideColumns(["updated_at"]);

            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->showQuickEditButton();
            $grid->enableDialogCreate();
            $grid->showColumnSelector();

            $grid->export();
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->like("itemId")->width(2);
                $filter->like("itemTitle")->width(2);
                $filter->like("sellerOpenId")->width(2);
                $filter->like("sellerNick")->width(2);
                $filter->between("commissionRate")
                    ->width(4);
                $filter->equal("type")
                    ->select(Product::$type)
                    ->width(2);
                
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
        return Show::make($id, new Product(), function (Show $show) {
            $show->field('id');
            $show->field('type')
                ->using(Product::$type);
            $show->field('skuId');
            $show->field('relSkuId');
            $show->field('skuDesc');
            $show->field('skuNick');
            $show->field('itemId');
            $show->field('relItemId');
            $show->field('itemTitle');
            $show->field('itemLinkUrl');
            $show->field('itemPicUrl');
            $show->field('price');
            $show->field('itemType')->using(Product::$itemType);
            // $show->field('status')->using(Product::$status);
            $show->field('sellerOpenId');
            $show->field('sellerNick');
            $show->field('commissionRate');
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
        return Form::make(new Product(), function (Form $form) {
            $form->display('id');
            $form->display('type');
            $form->display('skuId');
            $form->display('itemTitle');
            $form->display('price');
            // $form->switch('status');
            $form->number('commissionRate');
            $form->select('master_id')
                ->options(Master::all()->pluck('username', 'id'));
            $form->number('leader_rate');
            $form->display('sellerOpenId');
            $form->display('sellerNick');
            $form->display('relSkuId');
            $form->display('skuDesc');
            $form->display('skuNick');
            $form->display('itemId');
            $form->display('relItemId');
            $form->display('itemLinkUrl');
            $form->display('itemPicUrl');
            $form->display('itemType');
        
            $form->display('created_at');
            $form->display('updated_at');

            $form->saving(function (Form $form) {
                if($form->commissionRate && $form->leader_rate) {
                    if(($form->commissionRate - 1) < $form->leader_rate) {
                        return $form->response()->error('团长抽成比例不能大于自定义商品参与结算分销率');
                    }
                }
            });
        });
    }
}
