<?php

namespace App\Traits;

use App\Admin\Grid\Displayers\StatusShape;
use App\Models\Order;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid;

/**
 * 订单列表的紧凑列定义。
 *
 * 管理端与达人端共用同一套列，仅通过 $isAdmin 控制“店铺 / 来源”两处差异。
 * 目标：9 个数据列内看清订单、商品、金额、佣金、状态与时间，
 * 其余字段收进副行或“查看”详情页。
 *
 * 注意：Dcat 的 display() 闭包会把 $this 绑定到订单模型，
 * 所以格式化方法要通过捕获的 $that（控制器）调用，并保持 public。
 */
trait OrderGridColumns
{
    protected function orderGridColumns(Grid $grid, bool $isAdmin = false): void
    {
        $that = $this;

        $grid->addTableClass('order-grid');
        $grid->scrollbarX();
        $grid->disableRowSelector();
        $grid->fixColumns(2, 0);
        $grid->showColumnSelector();

        $grid->column('oid', '订单')
            ->display(function () use ($isAdmin, $that) {
                $html = '<div class="order-cell-main"><span class="order-no">'.e($this->oid).'</span></div>';

                $tags = [];
                $type = Order::$sellerType[$this->sellerType] ?? null;
                if ($type !== null) {
                    $tags[] = $that->orderTag($type);
                }
                if ($isAdmin && $this->relationLoaded('log') && $this->log) {
                    $tags[] = $that->orderTag(
                        $this->log->source ? '自动' : '手动',
                        $this->log->source ? 'order-tag-solid' : ''
                    );
                }

                if ($tags) {
                    $html .= '<div class="order-cell-sub">'.implode('', $tags).'</div>';
                }

                return $html;
            });

        $grid->column('product.itemTitle', '商品')
            ->display(function () use ($that) {
                $title = $this->product && $this->product->itemTitle
                    ? $this->product->itemTitle
                    : '—';

                return $that->orderCell($that->orderEllipsis($title, 200));
            });

        if ($isAdmin) {
            $grid->column('sellerNick', '店铺')
                ->display(function () use ($that) {
                    $name = $this->sellerNick ?: '—';

                    return $that->orderCell($that->orderEllipsis($name, 160));
                });
        }

        $grid->column('distributorName', '达人')
            ->display(function () use ($that) {
                $name = $this->distributorName ?: '—';

                return $that->orderCell($that->orderEllipsis($name, 160));
            });

        $grid->column('totalFee', '实付')
            ->display(function () use ($that) {
                $unit = $this->product && $this->product->price
                    ? $that->orderMoney($this->product->price).' × '.(int) $this->num
                    : '';

                return $that->orderCell($that->orderMoney($this->totalFee), $unit, 'text-align:right');
            })
            ->style('text-align:right')
            ->setHeaderAttributes(['style' => 'text-align:right']);

        $grid->column('cashPrice', '佣金')
            ->display(function () use ($that) {
                $main = $that->orderMoney($this->commissionTotal);
                if (! $this->is_rate) {
                    $main .= ' '.$that->orderTag('不计佣', 'order-tag-danger');
                }

                $sub = '一级 '.number_format(((int) $this->commission1) / 100, 2, '.', '')
                    .' · 二级 '.number_format(((int) $this->commission2) / 100, 2, '.', '');

                return $that->orderCell($main, $sub, 'text-align:right');
            })
            ->style('text-align:right')
            ->setHeaderAttributes(['style' => 'text-align:right']);

        $grid->column('status', '订单状态')
            ->display(function () use ($that) {
                $sub = '';
                if (! in_array((int) $this->refundStatus, [0, 70], true)) {
                    $sub = $that->orderRefundStatusTag($this->refundStatus);
                }

                return $that->orderCell($that->orderStatusTag($this->status), $sub);
            });

        $grid->column('cashStatus', '结算状态')
            ->display(function () use ($that) {
                return $that->orderCashStatusTag($this->cashStatus);
            });

        $grid->column('payTime', '时间')
            ->display(function () use ($that) {
                $main = $this->payTime
                    ? '<span class="order-time">'.e(substr($this->payTime, 0, 16)).'</span>'
                    : '<span class="order-empty">—</span>';
                $sub = $this->sendTime ? '发货 '.e(substr($this->sendTime, 0, 16)) : '';

                return $that->orderCell($main, $sub);
            })
            ->sortable();

        // 低频字段保留为可选列，默认不占宽度；需要时从“列选择”里打开。
        $grid->column('id')->sortable();
        $grid->column('created_at');
        $grid->column('updated_at')->sortable();
        $grid->hideColumns(['id', 'created_at', 'updated_at']);
    }

    public function orderCell(string $main, string $sub = '', string $style = ''): string
    {
        $attr = $style ? ' style="'.$style.'"' : '';

        return '<div class="order-cell"'.$attr.'>'
            .'<div class="order-cell-main">'.$main.'</div>'
            .($sub !== '' ? '<div class="order-cell-sub">'.$sub.'</div>' : '')
            .'</div>';
    }

    public function orderMoney($cents): string
    {
        return '<span class="order-money">¥'.number_format(((int) $cents) / 100, 2, '.', '').'</span>';
    }

    public function orderEllipsis($text, int $max = 220): string
    {
        $text = (string) $text;

        return '<span class="order-ellipsis" style="max-width:'.$max.'px" title="'.e($text).'">'.e($text).'</span>';
    }

    public function orderTag(string $text, string $class = ''): string
    {
        return '<span class="order-tag '.$class.'">'.e($text).'</span>';
    }

    public function orderStatusTag($status): string
    {
        $label = Order::$status[$status] ?? (string) $status;

        if (config('app.ui_skin') === 'mono') {
            return StatusShape::render($status, StatusShape::ORDER);
        }

        return $this->orderLabel($label, 'info');
    }

    public function orderRefundStatusTag($status): string
    {
        $label = Order::$refundStatus[$status] ?? (string) $status;

        if (config('app.ui_skin') === 'mono') {
            return StatusShape::render($status, StatusShape::REFUND);
        }

        return $this->orderLabel($label, 'warning');
    }

    public function orderCashStatusTag($value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '<span class="order-empty">—</span>';
        }

        if (config('app.ui_skin') === 'mono') {
            return StatusShape::render($value, StatusShape::CASH);
        }

        $colors = [
            '待结算' => 'default',
            '第一次已结算' => 'success',
            '第二次已结算' => 'success',
            '售后' => 'danger',
            '订单关闭' => 'danger',
        ];

        return $this->orderLabel($value, $colors[$value] ?? 'default');
    }

    public function orderLabel(string $text, string $color): string
    {
        if ($color === 'default') {
            return '<span class="label" style="background:#d2d6de;color:#555">'.e($text).'</span>';
        }

        $background = Admin::color()->get($color, $color);

        return '<span class="label" style="background:'.$background.'">'.e($text).'</span>';
    }
}
