<?php

namespace App\Admin\Controllers;

use App\Admin\Forms\CommissionSetting;
use App\Admin\Forms\SiteSetting;
use App\Admin\Forms\WechatSetting;
use Dcat\Admin\Layout\Content;
use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    /**
     * 网站设置
     */
    public function site(Content $content)
    {
        return $content
                    ->title('网站设置')
                    ->description('网站设置')
                    ->body(new SiteSetting());
    }

    /**
     * 结算设置
     */
    public function commission(Content $content)
    {
        return $content
                    ->title('结算设置')
                    ->description('订单结算以发货时间计算，订单结算比例设置')
                    ->body(new CommissionSetting());
    }

    /**
     * 视频号小店设置
     */
    public function wechat(Content $content)
    {
        return $content
                    ->title('视频号小店设置')
                    ->description('视频号小店的相关设置')
                    ->body(new WechatSetting());
    }
}