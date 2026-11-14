<?php

namespace App\Admin\Renderable;

use App\Models\Distributor as ModelsDistributor;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\LazyRenderable;

class Distributor extends LazyRenderable
{
    public function grid(): Grid
    {
        $master_id = $this->master_id;
        $builder = ModelsDistributor::query()->where("master_id", $master_id);
        return Grid::make($builder, function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('type')
                ->using(ModelsDistributor::$type)
                ->label([
                    0 => "cyan",
                    1 => "custom"
                ]);
            $grid->column('distributorId', '达人ID')->copyable();
            $grid->column('distributorName', '达人昵称');
            $grid->column('status', '状态')
                ->radio(ModelsDistributor::$status)
                ->label([
                    0 => 'gray',
                    1 => 'success',
                    2 => 'danger',
                ]);
            $grid->column('created_at');

            $grid->paginate(10);
            $grid->disableActions();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
        
                $filter->equal("type", "类型")
                    ->select(ModelsDistributor::$type)
                    ->width(3);
                $filter->like("distributorId", '达人ID')->width(3);
                $filter->equal("status", '状态')
                    ->select(ModelsDistributor::$status)
                    ->width(3);
            });
        });
    }
}