<?php

namespace App\Master\Controllers;

use App\Models\Distributor;
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
        $builder = Distributor::query()
                    ->where("master_id", Admin::user()->id);

        return Grid::make($builder, function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('type')
                ->using(Distributor::$type)
                ->label([
                    0 => "cyan",
                    1 => "custom"
                ]);
            $grid->column('distributorId')->copyable();
            $grid->column('distributorName');
            // $grid->column('master_id');
            $grid->column('status')
                ->using(Distributor::$status)
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
        
            $grid->quickSearch(["distributorId"]);

            $grid->disableBatchDelete();
            $grid->showQuickEditButton();
            $grid->enableDialogCreate();
            $grid->showColumnSelector();
            $grid->disableEditButton();
            $grid->disableViewButton();
            $grid->disableDeleteButton();

            // $grid->actions(function (Grid\Displayers\Actions $actions) {
            //     if($actions->row->status) {
            //         $actions->disableQuickEdit();
            //     }
            // });
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
            $show->field('distributorName');
            $show->field('master_id');
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
        return Form::make(new Distributor(), function (Form $form) {
            $id = $form->getKey();
            $user_id = Admin::user()->id;

            $form->display('id');

            if($form->model()->status){
                $form->display('distributorId');
            } else {
                $form->text('distributorId')
                ->required()
                ->creationRules(['required', "unique:distributors"])
                ->updateRules(['required', "unique:distributors,distributorId,$id"]);
            }

            $form->text('distributorName')->required();
            $form->hidden('master_id')
                ->value($user_id);
            $form->select('type')
                ->options(Distributor::$type)
                ->default(0);
            $form->textarea('remark');
        
            $form->display('created_at');
            $form->display('updated_at');

            $form->disableViewCheck();
            $form->disableDeleteButton();
        })->deleting(function (Form $form) {
            $data = $form->model()->toArray();
            if($data[0]["master_id"] != Admin::user()->id) {
                return $form->response()->error('无权限删除');
            }
        });
    }
}
