<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\AddMasterChild;
use App\Admin\Actions\Grid\ShowCash;
use App\Admin\Renderable\Distributor;
use App\Admin\Renderable\Master as RenderableMaster;
use App\Models\Master;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class MasterController extends AdminController
{
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Grid::make(Master::withCount(['distributors', 'childrens'])->with('parent'), function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('username');
            $grid->column('cash_money')->help("审核通过的提现金额");
            $grid->column('type')
                ->using(Master::$type)
                ->label();
            $grid->column('realname');
            $grid->column('account');
            $grid->column('canCommission')->sortable();
            $grid->column('rate1');
            $grid->column('rate2');
            $grid->column('wechat_rate1');
            $grid->column('wechat_rate2');
            $grid->column('distributors_count', '达人ID')
                // ->display('查看')
                ->if(function () {
                    return $this->distributors_count > 0;
                })
                ->modal(function ($modal) {
                    // 设置弹窗标题
                    $modal->title($this->username);

                    return Distributor::make(["master_id" => $this->id]);
                });
            $grid->column('childrens_count', '下级')
                ->if(function () {
                    return $this->childrens_count > 0;
                })
                ->modal(function ($modal) {
                    // 设置弹窗标题
                    $modal->title($this->username . " 的下级列表");

                    return RenderableMaster::make(["master_id" => $this->id]);
                });
            $grid->column('parent.username');
            $grid->column('parent_rate')->editable();
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();
        
            $grid->quickSearch(["id", "username", "parent.username"]);

            $grid->showColumnSelector();
            $grid->hideColumns(['account']);

            $grid->showQuickEditButton();
            $grid->enableDialogCreate();
            $grid->showColumnSelector();
            $grid->disableEditButton();
            $grid->disableViewButton();

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                // $actions->append(new ShowCash());
                // 达人统计
                $url = admin_url("master/".$actions->row->id."/chart");
                $actions->append('<a href="'.$url.'">统计</a>');

                // 流水明细
                $url = admin_url("money?master_id=".$actions->row->id);
                $actions->append('<a href="'.$url.'" style="margin-left:5px;">流水明细</a>');

                // 跳转到达人名下的达人ID列表
                $url = admin_url("master/distributor?master[username]=".$actions->row->username);
                $actions->append('<a href="'.$url.'" style="margin-left:5px;margin-right:5px;"><i class="fa fa-link"></i></a>');
            
                
                $actions->append(new AddMasterChild());
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
        return Show::make($id, new Master(), function (Show $show) {
            $show->field('id');
            $show->field('username');
            $show->field('remember_token');
            $show->field('cash_money');
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
        return Form::make(new Master(), function (Form $form) {
            $id = $form->getKey();

            $form->display('id');
            $form->text('username')
                ->required()
                ->creationRules(['required', "unique:masters"])
                ->updateRules(['required', "unique:masters,username,$id"]);

            if ($id) {
                $form->password('password')
                    ->minLength(5)
                    ->maxLength(20)
                    ->customFormat(function () {
                        return '';
                    });
            } else {
                $form->password('password')
                    ->required()
                    ->minLength(5)
                    ->maxLength(20);
            }

            $form->password('password_confirmation')->same('password');
            $form->ignore(['password_confirmation']);

            $form->hidden("parent_rate");
            $form->radio('type')
                ->options(Master::$type);
            $form->text('realname');
            $form->text('account');
            $form->rate('rate1')
                ->default(0)
                ->help("订单第一笔佣金在订单总佣金中所占比例，未设置则采用系统设置比例");
            $form->rate('rate2')
                ->default(0)
                ->help("订单第二笔佣金在订单总佣金中所占比例，未设置则采用系统设置比例");

            $form->rate('wechat_rate1')
                ->default(0)
                ->help("订单第一笔佣金在订单总佣金中所占比例，未设置则采用系统设置比例");
            $form->rate('wechat_rate2')
                ->default(0)
                ->help("订单第二笔佣金在订单总佣金中所占比例，未设置则采用系统设置比例");
        
            $form->display('created_at');
            $form->display('updated_at');
        })->saving(function (Form $form) {
            if($form->rate1 && $form->rate2 && ($form->rate1 + $form->rate2 != 100)) {
                return $form->response()->error('快手结算比例设置的不正确~');
            }

            if($form->wechat_rate1 && $form->wechat_rate2 && ($form->wechat_rate1 + $form->wechat_rate2 != 100)) {
                return $form->response()->error('视频号结算比例设置的不正确~');
            }

            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }

            if (!$form->password) {
                $form->deleteInput('password');
            }

            if(!$form->parent_rate) {
                $form->parent_rate = 0;
            }

            if(!$form->type) {
                $form->type = 0;
            }

            $form->rate1 = is_null($form->rate1) ? 0 : $form->rate1;
            $form->rate2 = is_null($form->rate2) ? 0 : $form->rate2;
            $form->wechat_rate1 = is_null($form->wechat_rate1) ? 0 : $form->wechat_rate1;
            $form->wechat_rate2 = is_null($form->wechat_rate2) ? 0 : $form->wechat_rate2;
        });
    }
}
