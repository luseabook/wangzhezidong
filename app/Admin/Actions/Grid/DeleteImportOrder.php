<?php

namespace App\Admin\Actions\Grid;

use App\Models\Order;
use App\Models\OrderImport;
use App\Models\OrderLog;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Traits\HasPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Dcat\EasyExcel\Excel;
use Illuminate\Support\Facades\DB;

class DeleteImportOrder extends RowAction
{
    /**
     * @return string
     */
	protected $title = '删除导入订单';

    public function title()
    {
        return "<button class='btn btn-danger' style='min-width:70px;'>{$this->title}</button>";
    }

    /**
     * Handle the action request.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Request $request)
    {
        $id = $this->getKey();
        $log = OrderLog::query()->findOrFail($id);
        // $oids = [];
        // $import = new OrderImport();
        // $import->setNowTable(date('Y_m'))
        //     ->where("order_log_id", $log->id)
        //     ->chunk(1000, function($items) use(&$oids) {
        //         foreach ($items as $item) {
        //             $row = unserialize($item->row);
        //             $oids[] = trim($row[0]);
        //         }
        //     });

        // Order::query()->whereIn('oid', $oids)
        //     ->delete();
        DB::transaction(function() use ($log) {
            Order::query()
                ->where("log_id", $log->id)
                ->delete();
            $log->status = 15;
            $log->save();
        });

        return $this->response()->success("删除成功")->refresh();
    }

    /**
	 * @return string|array|void
	 */
	public function confirm()
	{
		return ['删除?', '确定要删除导入的订单数据吗？'];
	}

    /**
     * @param Model|Authenticatable|HasPermissions|null $user
     *
     * @return bool
     */
    protected function authorize($user): bool
    {
        return true;
    }

    /**
     * @return array
     */
    protected function parameters()
    {
        return [];
    }
}
