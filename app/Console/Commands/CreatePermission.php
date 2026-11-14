<?php

namespace App\Console\Commands;

use Dcat\Admin\Models\Permission;
use Dcat\Admin\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreatePermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:permission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成后台权限';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $createdAt = date("Y-m-d H:i:s");
        Permission::truncate();
        Permission::insert([
            [
                'id'          => 1,
                'name'        => 'Auth management',
                'slug'        => 'auth-management',
                'http_method' => '',
                'http_path'   => '',
                'parent_id'   => 0,
                'order'       => 1,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 2,
                'name'        => 'Users',
                'slug'        => 'users',
                'http_method' => '',
                'http_path'   => '/auth/users*',
                'parent_id'   => 1,
                'order'       => 2,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 3,
                'name'        => 'Roles',
                'slug'        => 'roles',
                'http_method' => '',
                'http_path'   => '/auth/roles*',
                'parent_id'   => 1,
                'order'       => 3,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 4,
                'name'        => 'Permissions',
                'slug'        => 'permissions',
                'http_method' => '',
                'http_path'   => '/auth/permissions*',
                'parent_id'   => 1,
                'order'       => 4,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 5,
                'name'        => 'Menu',
                'slug'        => 'menu',
                'http_method' => '',
                'http_path'   => '/auth/menu*',
                'parent_id'   => 1,
                'order'       => 5,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 6,
                'name'        => 'Extension',
                'slug'        => 'extension',
                'http_method' => '',
                'http_path'   => '/auth/extensions*',
                'parent_id'   => 1,
                'order'       => 6,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 7,
                'name'        => '快手店铺管理',
                'slug'        => 'sellers',
                'http_method' => '',
                'http_path'   => '/sellers*',
                'parent_id'   => 0,
                'order'       => 7,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 8,
                'name'        => '视频号小店',
                'slug'        => 'wechat',
                'http_method' => '',
                'http_path'   => '/wechat*',
                'parent_id'   => 0,
                'order'       => 8,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 9,
                'name'        => '达人管理',
                'slug'        => 'master',
                'http_method' => '',
                'http_path'   => '',
                'parent_id'   => 0,
                'order'       => 9,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 10,
                'name'        => '达人菜单管理',
                'slug'        => 'master-menu',
                'http_method' => '',
                'http_path'   => '/master/menu*',
                'parent_id'   => 9,
                'order'       => 10,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 11,
                'name'        => '达人管理',
                'slug'        => 'masters',
                'http_method' => '',
                'http_path'   => '/master*',
                'parent_id'   => 9,
                'order'       => 11,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 12,
                'name'        => '达人ID管理',
                'slug'        => 'master-distributor',
                'http_method' => '',
                'http_path'   => '/master/distributor*',
                'parent_id'   => 9,
                'order'       => 12,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 13,
                'name'        => '提现列表',
                'slug'        => 'master-cash',
                'http_method' => '',
                'http_path'   => '/master/cash*',
                'parent_id'   => 9,
                'order'       => 13,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 14,
                'name'        => '流水明细',
                'slug'        => 'money',
                'http_method' => '',
                'http_path'   => '/money*',
                'parent_id'   => 0,
                'order'       => 14,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 15,
                'name'        => '产品管理',
                'slug'        => 'product',
                'http_method' => '',
                'http_path'   => '/product*',
                'parent_id'   => 0,
                'order'       => 15,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 16,
                'name'        => '订单列表',
                'slug'        => 'order',
                'http_method' => '',
                'http_path'   => '/order*',
                'parent_id'   => 0,
                'order'       => 16,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 17,
                'name'        => '导入管理',
                'slug'        => 'upload',
                'http_method' => '',
                'http_path'   => '',
                'parent_id'   => 0,
                'order'       => 17,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 18,
                'name'        => '单文件导入',
                'slug'        => 'order-import',
                'http_method' => '',
                'http_path'   => '/order/import*',
                'parent_id'   => 17,
                'order'       => 18,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 19,
                'name'        => '多文件导入',
                'slug'        => 'order-import2',
                'http_method' => '',
                'http_path'   => '/order/import2*',
                'parent_id'   => 17,
                'order'       => 19,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 20,
                'name'        => '导入记录',
                'slug'        => 'order-log',
                'http_method' => '',
                'http_path'   => '/order/log*',
                'parent_id'   => 17,
                'order'       => 20,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 21,
                'name'        => '设置',
                'slug'        => 'setting',
                'http_method' => '',
                'http_path'   => '',
                'parent_id'   => 0,
                'order'       => 21,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 22,
                'name'        => '网站设置',
                'slug'        => 'setting-site',
                'http_method' => '',
                'http_path'   => '/settings/site*',
                'parent_id'   => 21,
                'order'       => 22,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 23,
                'name'        => '结算设置',
                'slug'        => 'setting-commission',
                'http_method' => '',
                'http_path'   => '/settings/commission*',
                'parent_id'   => 21,
                'order'       => 23,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 24,
                'name'        => '视频号小店设置',
                'slug'        => 'setting-wechat',
                'http_method' => '',
                'http_path'   => '/settings/wechat*',
                'parent_id'   => 21,
                'order'       => 24,
                'created_at'  => $createdAt,
            ],
            [
                'id'          => 25,
                'name'        => '数据统计',
                'slug'        => 'master-chart',
                'http_method' => '',
                'http_path'   => '/master/chart*',
                'parent_id'   => 0,
                'order'       => 25,
                'created_at'  => $createdAt,
            ],
        ]);

        $this->info("后台权限生成完成~");

        Role::truncate();
        Role::insert([
            [
                'id' => 1,
                'name'       => 'Administrator',
                'slug'       => Role::ADMINISTRATOR,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ], 
            [
                'id' => 2,
                'name'       => '财务',
                'slug'       => 'caiwu',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
            [
                'id' => 3,
                'name'       => '链接手',
                'slug'       => 'lianjieshou',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
            [
                'id' => 4,
                'name'       => '采购',
                'slug'       => 'caigou',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
            [
                'id' => 5,
                'name'       => '订单上传手',
                'slug'       => 'order-upload',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]
        ]);
        $this->info("后台角色生成完成~");

        DB::table("admin_role_menu")->truncate();
        DB::table("admin_role_permissions")->truncate();
        Role::find(2)->permissions()->save(Permission::find(7));
        Role::find(2)->permissions()->save(Permission::find(9));
        Role::find(2)->permissions()->save(Permission::find(10));
        Role::find(2)->permissions()->save(Permission::find(11));
        Role::find(2)->permissions()->save(Permission::find(12));
        Role::find(2)->permissions()->save(Permission::find(13));
        Role::find(2)->permissions()->save(Permission::find(14));
        Role::find(2)->permissions()->save(Permission::find(15));
        Role::find(2)->permissions()->save(Permission::find(16));

        Role::find(3)->permissions()->save(Permission::find(11));
        Role::find(3)->permissions()->save(Permission::find(12));
        Role::find(3)->permissions()->save(Permission::find(15));
       
        Role::find(4)->permissions()->save(Permission::find(15));

        Role::find(5)->permissions()->save(Permission::find(16));
        Role::find(5)->permissions()->save(Permission::find(17));
        Role::find(5)->permissions()->save(Permission::find(18));
        Role::find(5)->permissions()->save(Permission::find(19));
        Role::find(5)->permissions()->save(Permission::find(20));
        $this->info("后台角色配置完成~");

        Role::find(2)->menus()->save(Permission::find(8));
        Role::find(2)->menus()->save(Permission::find(9));
        Role::find(2)->menus()->save(Permission::find(10));
        Role::find(2)->menus()->save(Permission::find(11));
        Role::find(2)->menus()->save(Permission::find(12));
        Role::find(2)->menus()->save(Permission::find(13));
        Role::find(2)->menus()->save(Permission::find(14));
        Role::find(2)->menus()->save(Permission::find(15));
        Role::find(2)->menus()->save(Permission::find(16));

        Role::find(3)->menus()->save(Permission::find(9));
        Role::find(3)->menus()->save(Permission::find(11));
        Role::find(3)->menus()->save(Permission::find(12));
        Role::find(3)->menus()->save(Permission::find(15));
       
        Role::find(4)->menus()->save(Permission::find(15));

        Role::find(5)->menus()->save(Permission::find(16));
        Role::find(5)->menus()->save(Permission::find(17));
        Role::find(5)->menus()->save(Permission::find(18));
        Role::find(5)->menus()->save(Permission::find(19));
        Role::find(5)->menus()->save(Permission::find(20));
        $this->info("后台菜单配置完成~");
    }
}
