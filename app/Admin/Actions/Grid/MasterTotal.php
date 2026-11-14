<?php

namespace App\Admin\Actions\Grid;

use App\Admin\Metrics\Master\Total;
use App\Models\Cash;
use App\Models\Master;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Traits\HasPermissions;
use Dcat\Admin\Widgets\Modal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class MasterTotal extends RowAction
{
    /**
     * @return string
     */
	protected $title = '统计';

    public function render()
    {
        $cash = Cash::query()->find($this->getKey());
        $master = Master::query()->find($cash->master_id);

        return Modal::make()
            ->lg()
            ->title($master->username ?? "")
            ->body(new Total($master->id))
            ->button($this->title)
            ->xl();
    }
}
