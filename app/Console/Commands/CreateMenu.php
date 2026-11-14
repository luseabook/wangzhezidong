<?php

namespace App\Console\Commands;

use App\Models\MasterMenu;
use Dcat\Admin\Models\Menu;
use Illuminate\Console\Command;

class CreateMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:menu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成后台菜单';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $createdAt = date('Y-m-d H:i:s');
        Menu::truncate();
        Menu::insert([
            [
                'parent_id'     => 0,
                'order'         => 1,
                'title'         => 'Index',
                'icon'          => 'feather icon-bar-chart-2',
                'uri'           => '/',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 2,
                'title'         => 'Admin',
                'icon'          => 'feather icon-settings',
                'uri'           => '',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 2,
                'order'         => 3,
                'title'         => 'Users',
                'icon'          => '',
                'uri'           => 'auth/users',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 2,
                'order'         => 4,
                'title'         => 'Roles',
                'icon'          => '',
                'uri'           => 'auth/roles',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 2,
                'order'         => 5,
                'title'         => 'Permission',
                'icon'          => '',
                'uri'           => 'auth/permissions',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 2,
                'order'         => 6,
                'title'         => 'Menu',
                'icon'          => '',
                'uri'           => 'auth/menu',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 2,
                'order'         => 7,
                'title'         => 'Extensions',
                'icon'          => '',
                'uri'           => 'auth/extensions',
                'created_at'    => $createdAt,
            ],
            // [
            //     'parent_id'     => 0,
            //     'order'         => 8,
            //     'title'         => '店铺授权管理',
            //     'icon'          => 'feather icon-package',
            //     'uri'           => '/stores',
            //     'show'          => 0,
            //     'created_at'    => $createdAt,
            // ],
            [
                'parent_id'     => 0,
                'order'         => 8,
                'title'         => '快手店铺管理',
                'icon'          => 'feather icon-package',
                'uri'           => '/sellers',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 9,
                'title'         => '达人管理',
                'icon'          => 'feather icon-users',
                'uri'           => '',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 9,
                'order'         => 10,
                'title'         => '达人菜单管理',
                'icon'          => '',
                'uri'           => '/master/menu',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 9,
                'order'         => 11,
                'title'         => '达人管理',
                'icon'          => 'feather icon-users',
                'uri'           => '/master',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 9,
                'order'         => 12,
                'title'         => '达人ID管理',
                'icon'          => '',
                'uri'           => '/master/distributor',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 13,
                'title'         => '提现列表',
                'icon'          => 'fa fa-list',
                'uri'           => '/master/cash',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 14,
                'title'         => '流水明细',
                'icon'          => 'fa fa-money',
                'uri'           => '/money',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 15,
                'title'         => '产品管理',
                'icon'          => 'fa fa-product-hunt',
                'uri'           => '/product',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 16,
                'title'         => '订单列表',
                'icon'          => 'fa fa-list',
                'uri'           => '/order',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 17,
                'title'         => '导入管理',
                'icon'          => 'fa fa-upload',
                'uri'           => '',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 17,
                'order'         => 18,
                'title'         => '单文件导入',
                'icon'          => 'fa fa-upload',
                'uri'           => '/order/import',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 17,
                'order'         => 19,
                'title'         => '多文件导入',
                'icon'          => 'fa fa-upload',
                'uri'           => '/order/import2',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 17,
                'order'         => 20,
                'title'         => '导入记录',
                'icon'          => 'fa fa-list-alt',
                'uri'           => '/order/log',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 21,
                'title'         => '设置',
                'icon'          => 'feather icon-settings',
                'uri'           => '',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 21,
                'order'         => 22,
                'title'         => '网站设置',
                'icon'          => '',
                'uri'           => '/settings/site',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 21,
                'order'         => 23,
                'title'         => '结算设置',
                'icon'          => '',
                'uri'           => '/settings/commission',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 21,
                'order'         => 24,
                'title'         => '视频号小店设置',
                'icon'          => '',
                'uri'           => '/settings/wechat',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 25,
                'title'         => '数据统计',
                'icon'          => 'fa-area-chart',
                'uri'           => '/master/chart',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 8,
                'title'         => '视频号小店',
                'icon'          => 'feather icon-package',
                'uri'           => '/wechat',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 30,
                'title'         => '订单统计',
                'icon'          => 'fa fa-line-chart',
                'uri'           => '/order/statistics',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 15,
                'title'         => '团长产品',
                'icon'          => 'fa fa-product-hunt',
                'uri'           => '/product2',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 31,
                'title'         => '订单变更记录',
                'icon'          => 'feather icon-clock',
                'uri'           => 'order/history',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 32,
                'title'         => '待登记达人',
                'icon'          => 'feather icon-user-plus',
                'uri'           => 'distributor/unregistered',
                'created_at'    => $createdAt,
            ],
        ]);

        (new Menu())->flushCache();

        $this->info("后台菜单生成完成~");

        MasterMenu::truncate();
        MasterMenu::insert([
            [
                'parent_id'     => 0,
                'order'         => 1,
                'title'         => 'Index',
                'icon'          => 'feather icon-bar-chart-2',
                'uri'           => '/',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 2,
                'title'         => '达人ID管理',
                'icon'          => 'fa fa-users',
                'uri'           => '/distributor',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 3,
                'title'         => '订单列表',
                'icon'          => 'fa fa-list',
                'uri'           => '/order',
                'created_at'    => $createdAt,
            ],
            // [
            //     'parent_id'     => 0,
            //     'order'         => 4,
            //     'title'         => '售后订单',
            //     'icon'          => 'fa fa-list-alt',
            //     'uri'           => '/refund-order',
            //     'created_at'    => $createdAt,
            // ],
            [
                'parent_id'     => 0,
                'order'         => 5,
                'title'         => '提现明细',
                'icon'          => 'fa fa-list-ul',
                'uri'           => '/cash',
                'created_at'    => $createdAt,
            ],
            [
                'parent_id'     => 0,
                'order'         => 6,
                'title'         => '流水明细',
                'icon'          => 'fa fa-money',
                'uri'           => '/money',
                'created_at'    => $createdAt,
            ],
        ]);
        $this->info("达人菜单生成完成~");
    }
}
