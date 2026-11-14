<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\AuditDistributor;
use App\Models\Distributor;
use App\Models\Master;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class DistributorController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        Admin::style(
            <<<CSS
    .ie-display {
        color: white;
    }
CSS            
        );

        return Grid::make(Distributor::with(['master']), function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('type')
                ->using(Distributor::$type)
                ->label([
                    0 => "cyan",
                    1 => "custom"
                ]);
            $grid->column('distributorId')->copyable();
            $grid->column('distributorName');
            $grid->column('master.username');
            $grid->column('status')
                // ->display(function($v) {
                //     return Distributor::$status[$v];
                // })
                ->radio(Distributor::$status)
                ->label([
                    0 => 'gray',
                    1 => 'success',
                    2 => 'danger',
                ]);
            $grid->column('remark')
                ->if(function() {
                    return $this->remark;
                })
                ->display("详情")
                ->expand(function () {
                    $card = new Card(null, $this->remark);
            
                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();

            $grid->showQuickEditButton();
            $grid->enableDialogCreate();
            $grid->showColumnSelector();
            $grid->disableEditButton();
            $grid->disableViewButton();
            $grid->disableDeleteButton();

            $grid->batchActions(new AuditDistributor());
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
        
                $filter->equal("type")
                    ->select(Distributor::$type)
                    ->width(2);
                $filter->like("distributorId")->width(2);
                $filter->like("master.username")->width(2);
                $filter->equal("status")
                    ->select(Distributor::$status)
                    ->width(3);
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
        return Show::make($id, new Distributor(), function (Show $show) {
            $show->field('id');
            $show->field('distributorId');
            $show->field('master_id');
            $show->field('type')
                ->using(Distributor::$type);
            $show->field('status');
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
        return Form::make(Distributor::with(['master']), function (Form $form) {
            $id = $form->getKey();

            $form->display('id');
            if($id) {
                $form->display('master.username');
            } else {
                $form->select('master_id')
                    ->options(Master::all()->pluck('username', 'id'))
                    ->required();
            }
            $form->text('distributorId')
                ->required()
                ->creationRules(['required', "unique:distributors"])
                ->updateRules(['required', "unique:distributors,distributorId,$id"]);
            $form->text('distributorName')->required();
            $form->select('type')
                ->options(Distributor::$type)
                ->default(0);
            $form->select('status')
                ->options(Distributor::$status)
                ->required();
            $form->textarea("remark");
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
