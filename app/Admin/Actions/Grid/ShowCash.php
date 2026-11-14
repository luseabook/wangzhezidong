<?php

namespace App\Admin\Actions\Grid;

use App\Models\Master;
use App\Traits\CashTrait;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;

class ShowCash extends RowAction
{
    use CashTrait;

    protected $title = '明细';

    public function render()
    {
        $master_id = $this->getKey();
        $canCommission = $this->canCommission($master_id);
        $allCommission = $this->allCommission($master_id);
        $unCommission = $this->unCommission($master_id);
        $cashed = $this->cashed($master_id);
        $master = Master::query()->find($master_id);

        $body = <<<HTML
        <div class="d-flex flex-wrap justify-content-between align-items-center mt-1" style="margin-bottom: 2px; min-height:105px;">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title my-1">可提现金额（元）</h5>
                    <p class="card-text">￥ {$canCommission}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title my-1">预结算金额（元）</h5>
                    <p class="card-text">￥ {$allCommission}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title my-1">待结算金额（元）</h5>
                    <p class="card-text">￥ {$unCommission}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title my-1">累计结算金额（元）</h5>
                    <p class="card-text">￥ {$cashed}</p>
                </div>
            </div>
        </div>
        HTML;
        
        return Modal::make()
            ->lg()
            ->title($master->username)
            ->body($body)
            ->button($this->title);
    }
}