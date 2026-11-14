<?php

namespace App\Admin\Forms;

use App\Models\Distributor;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;

class AuditDistributor extends Form implements LazyRenderable
{
    use LazyWidget;

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return mixed
     */
    public function handle(array $input)
    {
        $ids = explode(",", $input['ids'] ?? null);
        if(!$ids) {
            return $this->response()->error('参数错误~');
        }

        Distributor::query()
            ->whereIn('id', $ids)
            ->update([
                "status" => $input['status']
            ]);

        return $this
				->response()
				->success('审核成功~')
                ->refresh();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->select('status')
            ->options(Distributor::$status)
            ->required();
    
        $this->hidden('ids')->attribute('id', 'batch-audit-id');
    }
}
