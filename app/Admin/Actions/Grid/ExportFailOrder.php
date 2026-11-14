<?php

namespace App\Admin\Actions\Grid;

use App\Models\OrderImport;
use App\Models\OrderLog;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Traits\HasPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Dcat\EasyExcel\Excel;

class ExportFailOrder extends RowAction
{
    /**
     * @return string
     */
	protected $title = '导出';

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

        return $this->response()->download('export/order_import?id='.$id);
    }

    /**
	 * @return string|array|void
	 */
	public function confirm()
	{
		return ['导出?', '确定要导出失败的订单数据吗？'];
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
