<?php

namespace App\Admin\Forms;

use App\Models\Master;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Contracts\LazyRenderable;

class AddMasterChild extends Form implements LazyRenderable
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
        $id = $this->payload['id'];
        $master_id = $input['master_id'];
        
        // 判断是否有上级
        $master = Master::query()->find($master_id);
        if($master->parent_id != 0){
            return $this->response()->error($master->name . "已有上级，无法添加");
        }

        // 判断是否是下级
        $parent = Master::query()->find($id);
        if($parent->parent_id == $master->id){
            return $this->response()->error($parent->name . "的是上级是" . $master->name . "，无法添加");
        }

        $master->parent_id = $id;
        $master->parent_rate = $input['parent_rate'];
        $master->save();

        return $this
				->response()
				->success('添加成功')
				->refresh();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $id = $this->payload['id'];
        $this->select('master_id', "下级")
            ->options(Master::query()->where("id", "<>", $id)->where("parent_id", 0)->get()->pluck("name", "id"))
            ->required();

        $this->rate("parent_rate", "抽成百分比")->required();
    }
}
