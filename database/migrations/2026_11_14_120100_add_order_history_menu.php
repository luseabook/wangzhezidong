<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 把「订单变更记录」挂进后台菜单。
 *
 * 幂等：已存在（按 uri 判断）就不重复插入；找不到父级菜单时挂到顶层，
 * 保证在菜单尚未初始化的环境里也不会报错。
 */
return new class extends Migration
{
    private const URI = 'order/history';

    public function up(): void
    {
        if (!Schema::hasTable('admin_menu')) {
            return;
        }

        $exists = DB::table('admin_menu')->where('uri', self::URI)->exists();
        if ($exists) {
            return;
        }

        $parentId = DB::table('admin_menu')->where('uri', 'order')->value('id')
            ?: DB::table('admin_menu')->where('title', '订单管理')->value('id')
            ?: 0;

        $maxOrder = (int) DB::table('admin_menu')->where('parent_id', $parentId)->max('order');

        DB::table('admin_menu')->insert([
            'parent_id'  => $parentId,
            'order'      => $maxOrder + 1,
            'title'      => '订单变更记录',
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