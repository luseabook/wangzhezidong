<?php

namespace App\Master\Actions\Grid;

use App\Models\Cash;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Http\Request;

class DeleteCash extends RowAction
{
    protected $title = '删除';

    public function title()
    {
        return "<span class='ml-1'>{$this->title}</span>";
    }

    public function confirm()
    {
        return [
            "删除",
            "您确定要删除这行数据吗？",
        ];
    }

    /**
     * 处理请求
     *
     * @param Request $request
     *
     * @return \Dcat\Admin\Actions\Response
     */
    public function handle(Request $request)
    {
        // 获取当前行ID
        $id = $this->getKey();

        $cash = Cash::query()->find($id);
        if($cash->master_id != Admin::user()->id) {
            return $this->response()->error("无权限删除数据");
        }
        
        $cash->delete();

        // 返回响应结果并刷新页面
        return $this->response()->success("删除成功")->refresh();
    }
}