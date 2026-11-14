<?php

namespace App\Admin\Forms;

use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

class WechatSetting extends Form
{
    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return Response
     */
    public function handle(array $input)
    {
        admin_setting($input);

        return $this->response()->success('设置成功')->refresh();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->confirm('您确定要提交设置吗', '部分设置提交之后需要重新刷新一下浏览器才能生效');
        $this->number('wechat_days', '天数')
            ->default(admin_setting('wechat_days', 7))
            ->help("请求设置天数以来的订单");
        $this->number('wechat_page_size', '每页数量')
            ->max(100)
            ->default(admin_setting('wechat_page_size', 10))
            ->help("订单列表请求中每页数量，不超过100");
    }
}