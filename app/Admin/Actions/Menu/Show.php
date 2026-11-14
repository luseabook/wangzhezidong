<?php

namespace App\Admin\Actions\Menu;

use Dcat\Admin\Tree\RowAction;

class Show extends RowAction
{
    public function handle()
    {
        $key = $this->getKey();

        $menuModel = config('master.database.menu_model');
        $menu = $menuModel::find($key);

        $menu->update(['show' => $menu->show ? 0 : 1]);

        return $this
            ->response()
            ->success(trans('admin.update_succeeded'))
            ->location('master/menu');
    }

    public function title()
    {
        $icon = $this->getRow()->show ? 'icon-eye' : 'icon-eye-off';

        return "&nbsp;<i class='feather $icon'></i>&nbsp;";
    }
}
