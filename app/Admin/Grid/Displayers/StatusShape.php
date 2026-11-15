<?php

namespace App\Admin\Grid\Displayers;

use App\Models\Order;
use Dcat\Admin\Grid\Displayers\AbstractDisplayer;

/**
 * 单色状态形状展示器。
 *
 * 只在 UI_SKIN=mono 时启用；classic 皮肤继续使用 dcat 的 label。
 *
 * 形状编码：
 *   实心   mono-status-blue / mono-status-green / mono-status-teal
 *   描边   mono-status-amber
 *   斜纹   mono-status-red
 *   半填充 mono-status-purple
 *   灰色   mono-status-slate
 *   无标记 mono-status-none
 */
class StatusShape extends AbstractDisplayer
{
    public const ORDER = 'order';
    public const REFUND = 'refund';
    public const BOOL = 'bool';
    public const CASH = 'cash';

    public function display(string $type = self::ORDER)
    {
        return static::render($this->value, $type);
    }

    /**
     * 供复合单元格（订单状态 + 退款状态）复用。
     */
    public static function render($value, string $type = self::ORDER): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        [$labels, $classes] = static::maps($type);

        // 正常订单不打扰视线，只在售后列留一个弱化横线
        if ($type === self::REFUND && (int) $value === 0) {
            return '<span class="mono-status mono-status-none">—</span>';
        }

        $label = $labels[$value] ?? (string) $value;
        $class = $classes[$value] ?? 'mono-status-slate';

        if ($label === '') {
            return '';
        }

        return '<span class="mono-status '.e($class).'">'.e($label).'</span>';
    }

    protected static function maps(string $type): array
    {
        return match ($type) {
            self::ORDER => [
                Order::$status,
                [
                    0 => 'mono-status-purple',
                    10 => 'mono-status-slate',
                    30 => 'mono-status-amber',
                    40 => 'mono-status-blue',
                    50 => 'mono-status-teal',
                    70 => 'mono-status-green',
                    80 => 'mono-status-red',
                ],
            ],
            self::REFUND => [
                Order::$refundStatus,
                [
                    10 => 'mono-status-amber',
                    11 => 'mono-status-amber',
                    20 => 'mono-status-amber',
                    21 => 'mono-status-amber',
                    22 => 'mono-status-amber',
                    25 => 'mono-status-amber',
                    30 => 'mono-status-amber',
                    40 => 'mono-status-amber',
                    45 => 'mono-status-amber',
                    50 => 'mono-status-amber',
                    60 => 'mono-status-red',
                    70 => 'mono-status-slate',
                    80 => 'mono-status-amber',
                    85 => 'mono-status-amber',
                    90 => 'mono-status-amber',
                ],
            ],
            self::BOOL => [
                Order::$isRate,
                [
                    0 => 'mono-status-slate',
                    1 => 'mono-status-green',
                ],
            ],
            self::CASH => [
                [],
                [
                    '待结算' => 'mono-status-slate',
                    '第一次已结算' => 'mono-status-green',
                    '第二次已结算' => 'mono-status-green',
                    '售后' => 'mono-status-red',
                    '订单关闭' => 'mono-status-red',
                ],
            ],
            default => [[], []],
        };
    }
}