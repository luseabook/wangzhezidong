<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\ImportOrder;
use App\Admin\Exports\OrderExport;
use App\Admin\Grid\Displayers\StatusShape;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Traits\CashTrait;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;

class OrderController extends AdminController
{
    use CashTrait;
    
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $that = $this;
        $builder = Order::with(["product", "log"]);
        return Grid::make($builder, function (Grid $grid) use ($builder, $that) {
            $grid->model()->orderBy("payTime", "desc");
            $grid->column('id')->sortable();
            $grid->column('sellerType')
                ->using(Order::$sellerType)
                ->label([
                    0 => "cyan",
                    1 => "custom"
                ]);
            $grid->column('oid');
            $grid->column('log.source', '来源')
                ->using([0 => '手动', 1 => '自动'])
                ->label([0 => 'default', 1 => 'primary']);
            $grid->column('payTime');
            $grid->column('sendTime');
            if (config('app.ui_skin') === 'mono') {
                $grid->column('status')->displayUsing(StatusShape::class, [StatusShape::ORDER]);
                $grid->column('refundStatus')->displayUsing(StatusShape::class, [StatusShape::REFUND]);
            } else {
                $grid->column('status')
                    ->using(Order::$status)
                    ->label("info");
                $grid->column('refundStatus')
                    ->using(Order::$refundStatus)
                    ->label("custom");
            }
            $grid->column('sellerNick')
                ->limit(3)
                ->expand(function () {
                    $card = new Card(null, $this->sellerNick);
            
                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('distributorId');
            $grid->column('distributorName')
                ->limit(3)
                ->expand(function () {
                    $card = new Card(null, $this->distributorName);
            
                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('itemId');
            $grid->column('product.itemTitle')
                ->limit(3)
                ->expand(function () {
                    $card = new Card(null, $this->product ? $this->product->itemTitle : '');
            
                    return "<div style='padding:10px 10px 0'>$card</div>";
                });
            $grid->column('product.price')
                ->display(function($v) {
                    return $v / 100;
                })
                ->style('text-align:right')->setHeaderAttributes(['style' => 'text-align:right']);
            $grid->column('num')->style('text-align:right')->setHeaderAttributes(['style' => 'text-align:right']);
            $grid->column('totalFee')
                ->display(function($v) {
                    return $v / 100;
                })
                ->style('text-align:right')->setHeaderAttributes(['style' => 'text-align:right']);
            $grid->column('rate')->style('text-align:right')->setHeaderAttributes(['style' => 'text-align:right']);
            $grid->column('cashPrice')
                ->display(function() {
                    return $this->commissionTotal / 100;
                })
                ->style('text-align:right')->setHeaderAttributes(['style' => 'text-align:right']);
            if (config('app.ui_skin') === 'mono') {
                $grid->column('is_rate')->displayUsing(StatusShape::class, [StatusShape::BOOL]);
            } else {
                $grid->column('is_rate')
                    ->using(Order::$isRate)
                    ->label([
                        0 => "danger",
                        1 => "success",
                    ]);
            }
            $grid->column('rate1Price')
                ->display(function() {
                    return $this->commission1 / 100;
                })
                ->style('text-align:right')->setHeaderAttributes(['style' => 'text-align:right']);
            $grid->column('rate2Price')
                ->display(function() {
                    return $this->commission2 / 100;
                })
                ->style('text-align:right')->setHeaderAttributes(['style' => 'text-align:right']);
            if (config('app.ui_skin') === 'mono') {
                $grid->column('cashStatus')->displayUsing(StatusShape::class, [StatusShape::CASH]);
            } else {
                $grid->column('cashStatus')
                    ->label("cyan");
            }
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();

            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->disableCreateButton();
            $grid->disableBatchDelete();
            // $grid->scrollbarX();

            // $grid->tools(new ImportOrder());

            // 导出处理
            $grid->export(new OrderExport());

            $grid->header(function ($collection) use ($grid, $builder, $that) {
                if(request()->has("master") && request()->input("master")) {
                    $query = $builder;
            
                    // 拿到表格筛选 where 条件数组进行遍历
                    $grid->model()->getQueries()->unique()->each(function ($value) use (&$query) {
                        if (in_array($value['method'], ['paginate', 'get', 'orderBy', 'orderByDesc'], true)) {
                            return;
                        }
                
                        $query = call_user_func_array([$query, $value['method']], $value['arguments'] ?? []);
                    });
                
                    // 查出统计数据
                    // 预估总佣金
                    $allCommission = $that->allCommissionByModel((clone $query));
                    $doneCommission = $that->doneCommissionByModel((clone $query));
                    $unCommission = bcsub($allCommission, $doneCommission, 2);

                    $body = <<<HTML
                    <div class="card mt-2">
                        <div class="card-body">
                        <span class="text-danger">预估总佣金：{$allCommission}</span>
                        <span class="text-success">预估剩余佣金：{$unCommission}</span>
                        <span class="text-info">已结算佣金：{$doneCommission}</span>
                        </div>
                    </div>
                    HTML;

                    // 自定义组件
                    return $body;
                }
                
                return <<<HTML
                    <div class="card mt-2">
                        <div class="card-body">
                        选择达人账号才会出现统计
                        </div>
                    </div>
                    HTML;
            });

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();

                $filter->equal("sellerType")
                    ->select(Order::$sellerType)
                    ->width(3);
                $filter->like("oid")->width(3);
                $filter->like("sellerOpenId")->width(3);
                $filter->like("distributorId")->width(3);
                $filter->like("itemId")->width(3);
                $filter->where("master", function ($query) {
                    if($this->input) {
                        $master = Master::query()->with("distributors")->find($this->input);
                        $distributor_ids = $master->distributors->pluck("distributorId")->toArray();
                        $query->whereIn("distributorId", $distributor_ids);
                    }
                }, "达人帐号")
                    ->select(Master::all()->pluck("username", "id"))
                    ->width(3);
                $filter->like("product.itemTitle")->width(3);
                $filter->equal("status")
                    ->select(Order::$status)
                    ->width(3);
                $filter->equal("refundStatus")
                    ->select(Order::$refundStatus)
                    ->width(3);
                $filter->where("is_refund", function ($query) {
                        if($this->input) {
                            $query->ofRefund();
                        } else {
                            $query->whereIn("refundStatus", [0, 70]);
                        }
                    })
                    ->select(Order::$isRefund)
                    ->width(3);
                $filter->equal("cpsPayStatus")
                    ->select(Order::$cpsPayStatus)
                    ->width(3);
                $filter->myCustomBetween("payTime")->date()->width(4);
                $filter->myCustomBetween("sendTime")->date()->width(4);
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
        // 变更历史：按订单ID取最近 100 条（只读，用于"有据可依"）
        $histories = OrderHistory::query()
            ->where('order_id', $id)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $historyHtml = $this->renderHistory($histories, $id);

        return Show::make($id, new Order(), function (Show $show) use ($historyHtml) {
            $show->field('id');
            $show->field('sellerType');
            $show->field('oid');
            $show->field('sellerOpenId');
            $show->field('sellerNick');
            $show->field('expressFee');
            $show->field('discountFee');
            $show->field('totalFee');
            $show->field('status')
                ->using(Order::$status);
            $show->field('cpsType');
            $show->field('payTime');
            $show->field('sendTime');
            $show->field('refundTime');
            $show->field('createTime');
            $show->field('updateTime');
            $show->field('orderDetail');
            $show->field('distributorId');
            $show->field('distributorName');
            $show->field('cpsStatus');
            $show->field('commissionRate');
            $show->field('estimatedIncome');
            $show->field('platformDpRate');
            $show->field('settlementBizType');
            $show->field('promoterServiceInCome');
            $show->field('promoterExcitationInCome');
            $show->field('orderChannel');
            $show->field('cpsDetail');
            $show->field('skuId');
            $show->field('relSkuId');
            $show->field('skuNick');
            $show->field('itemId');
            $show->field('relItemId');
            $show->field('originalPrice');
            $show->field('goodDiscountFee');
            $show->field('price');
            $show->field('cpsPayStatus')
                ->using(Order::$cpsPayStatus);
            $show->field('commissionTotal');
            $show->field('commission_rate');
            $show->field('commission1');
            $show->field('commission1_rate');
            $show->field('commission2');
            $show->field('commission2_rate');
            $show->field('created_at');
            $show->field('updated_at');

            $show->html($historyHtml);

            $show->disableDeleteButton();
            $show->disableEditButton();
        });
    }

    /**
     * 渲染订单变更历史区块
     */
    private function renderHistory($histories, $orderId): string
    {
        $count = $histories->count();
        $rows = '';

        foreach ($histories as $h) {
            $field = e($h->fieldLabel());
            $old = $h->old_value === null || $h->old_value === '' ? '<span style="color:#bbb">空</span>' : e($h->old_value);
            $new = $h->new_value === null || $h->new_value === '' ? '<span style="color:#bbb">空</span>' : e($h->new_value);
            $source = e($h->sourceLabel());
            $color = [0 => '#1c7ed6', 1 => '#2f9e44', 2 => '#868e96'][$h->source] ?? '#868e96';
            $time = optional($h->changed_at)->format('Y-m-d H:i:s') ?? '';
            $op = $h->operator ? e($h->operator) : '<span style="color:#bbb">—</span>';
            $batch = $h->order_log_id ? ('#' . $h->order_log_id) : '—';

            $rows .= "<tr>
                <td style='white-space:nowrap;color:#666'>{$time}</td>
                <td>{$field}</td>
                <td style='color:#c92a2a'>{$old}</td>
                <td style='color:#2b8a3e'>{$new}</td>
                <td><span style='color:#fff;background:{$color};padding:1px 6px;border-radius:8px;font-size:12px'>{$source}</span></td>
                <td>{$op}</td>
                <td style='color:#999'>{$batch}</td>
            </tr>";
        }

        if ($count === 0) {
            return '<div class="card" style="margin-top:15px">
                <div class="card-header"><h4 style="margin:0">变更记录</h4></div>
                <div class="card-body" style="color:#999">暂无变更记录</div>
            </div>';
        }

        $more = $count >= 100 ? "<div style='padding:8px 0;color:#999'>仅显示最近 100 条，<a href='/admin/order/history?order_id={$orderId}'>查看全部</a></div>" : '';

        return '<div class="card" style="margin-top:15px">
            <div class="card-header"><h4 style="margin:0">变更记录 <span style="color:#999;font-size:13px">共 ' . $count . ' 条</span></h4></div>
            <div class="card-body" style="padding:0">
                <table class="table" style="margin:0">
                    <thead><tr style="background:#fafafa">
                        <th>时间</th><th>字段</th><th>原值</th><th>新值</th><th>来源</th><th>操作人</th><th>批次</th>
                    </tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
                ' . $more . '
            </div>
        </div>';
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Form::make(new Order(), function (Form $form) {
            $form->display('id');
            $form->text('oid');
            $form->text('sellerOpenId');
            $form->text('sellerNick');
            $form->text('expressFee');
            $form->text('discountFee');
            $form->text('totalFee');
            $form->text('status');
            $form->text('cpsType');
            $form->text('payTime');
            $form->text('sendTime');
            $form->text('refundTime');
            $form->text('createTime');
            $form->text('updateTime');
            $form->text('orderDetail');
            $form->text('distributorId');
            $form->text('distributorName');
            $form->text('cpsStatus');
            $form->text('commissionRate');
            $form->text('estimatedIncome');
            $form->text('platformDpRate');
            $form->text('settlementBizType');
            $form->text('promoterServiceInCome');
            $form->text('promoterExcitationInCome');
            $form->text('orderChannel');
            $form->text('cpsDetail');
            $form->text('skuId');
            $form->text('relSkuId');
            $form->text('skuNick');
            $form->text('itemId');
            $form->text('relItemId');
            $form->text('originalPrice');
            $form->text('goodDiscountFee');
            $form->text('price');
            $form->text('cpsPayStatus');
        
            $form->display('created_at');
            $form->display('updated_at');
        });
    }
}
