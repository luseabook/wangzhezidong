<?php

namespace App\Admin\Forms;

use Dcat\Admin\Widgets\Form;
use Symfony\Component\HttpFoundation\Response;

class SiteSetting extends Form
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

        return $this->response()->success('设置成功')->location();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->text('admin_site_title', "管理端标题")
            ->default(admin_setting('admin_site_title'));
        $this->text('admin_site_logo_text', "管理端LOGO文字")
            ->help('文本LOGO显示的优先度低于图片，当没有上传图片作为LOGO时，此项将生效。')
            ->default(admin_setting('admin_site_logo_text'));
        $this->image('admin_site_logo', '管理端LOGO')
            ->autoUpload()
            ->uniqueName()
            ->help("推荐图片大小：225*225")
            ->default(admin_setting('admin_site_logo'));
        $this->image('admin_site_logo_mini', '管理端LOGO(微缩)')
            ->autoUpload()
            ->uniqueName()
            ->help("推荐图片大小：64*64")
            ->default(admin_setting('admin_site_logo_mini'));

        $this->text('master_site_title', "达人端标题")
            ->default(admin_setting('master_site_title'));
        $this->text('master_site_logo_text', "达人端LOGO文字")
            ->help('文本LOGO显示的优先度低于图片，当没有上传图片作为LOGO时，此项将生效。')
            ->default(admin_setting('master_site_logo_text'));
        $this->image('master_site_logo', '达人端LOGO')
            ->autoUpload()
            ->uniqueName()
            ->help("推荐图片大小：225*225")
            ->default(admin_setting('master_site_logo'));
        $this->image('master_site_logo_mini', '达人端LOGO(微缩)')
            ->autoUpload()
            ->uniqueName()
            ->help("推荐图片大小：64*64")
            ->default(admin_setting('master_site_logo_mini'));
        $this->image('master_site_login', '达人端登录背景图')
            ->autoUpload()
            ->uniqueName()
            ->help("推荐图片大小：5120*2880")
            ->default(admin_setting('master_site_login'));
        $this->text('master_site_beian', "备案")
            ->default(admin_setting('master_site_beian'));
    }
}