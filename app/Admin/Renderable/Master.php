<?php

namespace App\Admin\Renderable;

use App\Admin\Actions\Grid\DeleteMasterChild;
use App\Admin\Actions\Grid\UpdateMasterChildRate;
use App\Models\Master as ModelsMaster;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\LazyRenderable;

class Master extends LazyRenderable
{
    public function grid(): Grid
    {
        $master_id = $this->master_id;
        $builder = ModelsMaster::query()->where("parent_id", $master_id);
        return Grid::make($builder, function (Grid $grid) {
            $grid->setResource("master");

            $grid->column('id')->sortable();
            $grid->column('username');
            // $grid->column('cash_money')->help("审核通过的提现金额");
            // $grid->column('type')
            //     ->using(ModelsMaster::$type)
            //     ->label();
            // $grid->column('realname');
            // $grid->column('account');
            // $grid->column('canCommission')->sortable();
            // $grid->column('rate1');
            // $grid->column('rate2');
            // $grid->column('wechat_rate1');
            // $grid->column('wechat_rate2');
            $grid->column('parent_rate', '抽成百分比');
            $grid->column('created_at');

            $grid->paginate(10);
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->disableViewButton();

            $grid->actions([
                new DeleteMasterChild(), 
            ]);

            $grid->quickSearch(['id', 'username']);
        });
    }
}