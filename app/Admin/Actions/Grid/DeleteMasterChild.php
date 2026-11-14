<?php

namespace App\Admin\Actions\Grid;

use App\Models\Master;
use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Traits\HasPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DeleteMasterChild extends RowAction
{
    /**
     * @return string
     */
	protected $title = '删除下级';

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
        $master = Master::query()->find($id);

        if(!$master) {
            return $this->response()->error('未找到下级，删除失败');
        }

        $master->parent_id = 0;
        $master->parent_rate = 0;
        $master->save();

        return $this->response()
            ->success("下级删除成功")
            ->refresh();
    }

    /**
	 * @return string|array|void
	 */
	public function confirm()
	{
		return ['删除下级?', '你确定要删除下级吗'];
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
