<?php

namespace App\Admin\Forms;

use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

class CommissionSetting extends Form
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

        // 重启队列
        Artisan::call("queue:restart");

        return $this->response()->success('设置成功')->refresh();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->confirm('您确定要提交设置吗', '部分设置提交之后需要重新刷新一下浏览器才能生效');
        $this->html("结算时间设置");
        $this->number('commission1_days', '第一次结算时间')
            ->default(admin_setting('commission1_days', 72))
            ->help("第一笔佣金在 已发货时间+自定义小时数 两个条件同时满足的条件下自动结算");
        $this->number('commission2_days', '第二次结算时间')
            ->default(admin_setting('commission2_days', 600))
            ->help("第二笔佣金在 已发货时间+自定义小时数+订单完成状态 三个条件同时满足的条件下自动结算");
        $this->divider();
        $this->html("结算比例设置");
        $this->rate('commission1_rate', '快手第一次结算比例')
            ->default(admin_setting('commission1_rate', 50))
            ->help("订单第一笔佣金在订单总佣金中所占比例");
        $this->rate('commission2_rate', '快手第二次结算比例')
            ->default(admin_setting('commission2_rate', 50))
            ->help("订单第二笔佣金在订单总佣金中所占比例");
        $this->rate('wechat_commission1_rate', '视频号第一次结算比例')
            ->default(admin_setting('wechat_commission1_rate', 50))
            ->help("订单第一笔佣金在订单总佣金中所占比例");
        $this->rate('wechat_commission2_rate', '视频号第二次结算比例')
            ->default(admin_setting('wechat_commission2_rate', 50))
            ->help("订单第二笔佣金在订单总佣金中所占比例");
    }
}