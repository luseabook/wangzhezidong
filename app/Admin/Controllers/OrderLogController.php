<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\DeleteImportOrder;
use App\Admin\Actions\Grid\ExportFailOrder;
use App\Models\OrderLog;
use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class OrderLogController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(new OrderLog(), function (Grid $grid) {
            $grid->model()->orderBy('id', 'desc');

            $grid->column('id')->sortable();
            $grid->column('type')
                ->using(OrderLog::$type)
                ->label([
                    0 => "cyan",
                    1 => "custom"
                ]);
            $grid->column('sellerOpenId');
            $grid->column('sellerNick');
            $grid->column('status')
                ->using(OrderLog::$status)
                ->label([
                    0 => "default",
                    4 => "cyan",
                    5 => "info",
                    6 => "danger",
                    9 => "custom",
                    10 => "success",
                    11 => "danger",
                ]);
            $grid->column('file')
                ->display(function($file) {
                    return url("storage/$file");
                })
                ->downloadable()
                ->setAttributes(['class' => 'font-hidden']);
            $grid->column('total');
            $grid->column('success');
            $grid->column('fail');
            $grid->column('unSend');
            $grid->column('unPayOrCps');
            $grid->column('created_at')->sortable();
            // $grid->column('updated_at')->sortable();
            $grid->column("failFile")
                ->display(function($file) {
                    return $file ? url("storage/export/$file") : "";
                })
                ->downloadable();

            $grid->disableCreateButton();
            $grid->disableViewButton();
            $grid->disableEditButton();
            $grid->disableBatchDelete();
            $grid->disableDeleteButton();
            // $grid->disableActions();

            // $grid->actions(function (Grid\Displayers\Actions $actions) {
            //     if ($actions->row->fail) {
            //         $actions->append(new ExportFailOrder());
            //     }
            // });
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                if($actions->row->status != 15) {
                    $actions->append(new DeleteImportOrder());
                }
            });
            $grid->header(function ($collection) {
                Admin::style(
                    <<<CSS
                    .card-header {
                        margin-top: 20px;
                    }
                CSS            
                );

                $html = "
状态解释：<br>
待处理：Excel文件上传到服务器<br>
导入中：Excel文件数据正在导入临时数据表<br>
已导入：Excel文件数据成功导入临时数据表<br>
导入失败：Excel文件数据导入临时数据表过程中出现程序报错<br>
处理中：临时数据表数据正在导入订单数据表<br>
已处理：临时数据表数据成功导入订单数据表<br>
处理失败：临时数据表数据导入订单数据表过程中出现程序报错
                ";
                return Card::make($html);
            });
        
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->like("sellerOpenId")->width(2);
                $filter->like("sellerNick")->width(2);
                $filter->equal("status")
                    ->select(OrderLog::$status)
                    ->width(3);
                $filter->equal("type")
                    ->select(OrderLog::$type)
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
        return Show::make($id, new OrderLog(), function (Show $show) {
            $show->field('id');
            $show->field('sellerOpenId');
            $show->field('sellerNick');
            $show->field('file');
            $show->field('total');
            $show->field('success');
            $show->field('fail');
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
        return Form::make(new OrderLog(), function (Form $form) {
            $form->display('id');
            $form->text('sellerOpenId');
            $form->text('sellerNick');
            $form->text('file');
            $form->text('total');
            $form->text('success');
            $form->text('fail');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
