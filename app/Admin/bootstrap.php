<?php

use App\Admin\Grid\Filter\Between;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use Dcat\Admin\Form;
use Dcat\Admin\Grid\Filter;
use Dcat\Admin\Show;
use Illuminate\Support\Facades\Storage;

/**
 * Dcat-admin - admin builder based on Laravel.
 * @author jqh <https://github.com/jqhph>
 *
 * Bootstraper for Admin.
 *
 * Here you can remove builtin form field:
 *
 * extend custom field:
 * Dcat\Admin\Form::extend('php', PHPEditor::class);
 * Dcat\Admin\Grid\Column::extend('php', PHPEditor::class);
 * Dcat\Admin\Grid\Filter::extend('php', PHPEditor::class);
 *
 * Or require js and css assets:
 * Admin::css('/packages/prettydocs/css/styles.css');
 * Admin::js('/packages/prettydocs/js/main.js');
 *
 */
/**
 * 自定义时间筛选
 */
Filter::extend('myCustomBetween', Between::class);

Admin::style(
    <<<CSS
    .font-hidden a {
        width: 50px;
        overflow: hidden;
        display: inline-block;
    }
CSS
);

/**
 * UI 皮肤（可选）：默认 classic，设置 UI_SKIN=mono 后启用黑白非线性皮肤。
 */
if (config('app.ui_skin') === 'mono') {
    Admin::css('/vendor/theme/tokens.css');
    Admin::css('/vendor/theme/admin.css');
    Admin::css('/vendor/theme/mono.css');
    Admin::script("document.documentElement.classList.add('mono');");
}

/**
 * 处理标题
 */
if (empty(admin_setting('admin_site_title'))) {
    $site_title = config('admin.name');
} else {
    $site_title = admin_setting('admin_site_title');
}
/**
 * 处理站点LOGO自定义
 */
if (empty(admin_setting('admin_site_logo'))) {
    $logo = empty(admin_setting('admin_site_logo_text')) ? config("admin.logo") : admin_setting('admin_site_logo_text');
} else {
    $logo = Storage::disk(config('admin.upload.disk'))->url(admin_setting('admin_site_logo'));
    $logo = "<img src='$logo'>";
}

/**
 * 处理站点LOGO-MINI自定义
 */
if (empty(admin_setting('admin_site_logo_mini'))) {
    $logo_mini = empty(admin_setting('admin_site_logo_text')) ? config("admin.logo_mini") : admin_setting('admin_site_logo_text');
} else {
    $logo_mini = Storage::disk(config('admin.upload.disk'))->url(admin_setting('admin_site_logo_mini'));
    $logo_mini = "<img src='$logo_mini'>";
}

config([
    'admin.logo' => $logo,
    'admin.logo-mini' => $logo_mini,
    "admin.name" => $site_title,
    "admin.title" => $site_title,
]);
