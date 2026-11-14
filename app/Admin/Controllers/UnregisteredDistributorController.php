<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Layout\Content;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * 待登记达人
 *
 * 分佣链路是「订单里的达人号 → 分销者表 → 达人」三层展开的：
 * 订单带的达人号如果没在分销者表里登记（或未启用），这单会被静默跳过，
 * 佣金永远是 0 且界面上看不到任何报错。自动同步后新人会不断出现，
 * 所以需要一个地方把"漏登记的人"直接摆出来。
 */
class UnregisteredDistributorController extends Controller
{
    public function index(Content $content)
    {
        $days = (int) request('days', 90);
        if ($days <= 0) $days = 90;
        $from = now()->subDays($days)->startOfDay();

        $rows = DB::table('orders as o')
            ->leftJoin('distributors as d', function ($join) {
                $join->on('d.distributorId', '=', 'o.distributorId')
                    ->where('d.status', '=', 1);
            })
            ->whereNotNull('o.distributorId')
            ->where('o.distributorId', '<>', '')
            ->whereNull('d.id')
            ->where('o.createTime', '>=', $from)
            ->groupBy('o.distributorId')
            ->selectRaw('o.distributorId as distributorId')
            ->selectRaw('MAX(o.distributorName) as distributorName')
            ->selectRaw('COUNT(*) as orders_cnt')
            ->selectRaw('SUM(o.totalFee) as gmv_cents')
            ->selectRaw('MIN(o.createTime) as first_at')
            ->selectRaw('MAX(o.createTime) as last_at')
            ->orderByDesc('orders_cnt')
            ->limit(500)
            ->get();

        $registered = DB::table('distributors')->where('status', 1)->count();
        $affect = $rows->sum('orders_cnt');
        $gmv = $rows->sum('gmv_cents');

        return $content
            ->header('待登记达人')
            ->description('这些达人号出现在订单里，但分销者列表中没有登记（或未启用），他们的订单不会产生佣金')
            ->body(view('admin.unregistered', compact('rows', 'days', 'registered', 'affect', 'gmv')));
    }
}