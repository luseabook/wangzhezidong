<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 把「待登记达人」挂进后台菜单。
 *
 * 背景：分佣是「订单达人号 → 分销者表 → 达人」三层展开的，
 * 达人号没登记时订单会被静默跳过、佣金为 0 且无任何报错。
 * 自动同步后新达人会不断出现，需要有人看到该补谁。
 */
return new class extends Migration
{
    private const URI = 'distributor/unregistered';

    public function up(): void
    {
        if (!Schema::hasTable('admin_menu')) {
            return;
        }
        if (DB::table('admin_menu')->where('uri', self::URI)->exists()) {
            return;
        }

        $parentId = DB::table('admin_menu')->where('uri', 'master/distributor')->value('id')
            ?: DB::table('admin_menu')->where('uri', 'master')->value('id')
            ?: DB::table('admin_menu')->where('title', '达人管理')->value('id')
            ?: 0;

        $maxOrder = (int) DB::table('admin_menu')->where('parent_id', $parentId)->max('order');

        DB::table('admin_menu')->insert([
            'parent_id'  => $parentId,
            'order'      => $maxOrder + 1,
            'title'      => '待登记达人',
            'icon'       => '',
            'uri'        => self::URI,
            'extension'  => '',
            'show'       => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_menu')) {
            return;
        }
        DB::table('admin_menu')->where('uri', self::URI)->delete();
    }
};