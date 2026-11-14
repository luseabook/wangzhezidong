<?php

namespace App\Admin\Actions\Grid;

use App\Admin\Forms\AuditDistributor as FormsAuditDistributor;
use Dcat\Admin\Grid\BatchAction;
use Dcat\Admin\Widgets\Modal;
use Illuminate\Http\Request;

class AuditDistributor extends BatchAction
{
    protected $title = "审核";

    /**
     * @return string
     */
    public function title()
    {
        return '<a href="#"><i class="fa fa-check" aria-hidden="true"></i> '.$this->title . '</a>';
    }

    /**
     * Handle the action request.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function render()
    {
        $form = FormsAuditDistributor::make();

        return Modal::make()
            ->lg()
            ->title($this->title)
            ->body($form)
            ->onLoad($this->getModalScript())
            ->onShow($this->getModalShowScript())
            ->button($this->title());
    }


    protected function getModalScript()
    {
        // 弹窗显示后往隐藏的id表单中写入批量选中的行ID
        return <<<JS
        try{
            // 获取选中的ID数组
            var key = {$this->getSelectedKeysScript()}
            $('#batch-audit-id').val(key);  
        }catch(err){
            console.error(err)
        }  
    JS;
    }

    protected function getModalShowScript()
    {
        // 弹窗显示后往隐藏的id表单中写入批量选中的行ID
        return <<<JS
        try{
            // 获取选中的ID数组
            var key = {$this->getSelectedKeysScript()}
            if (key.length === 0) {
                Dcat.reload();
                Dcat.warning('未选中数据~');
                return false;
            }
        }catch(err){
            console.error(err)
            Dcat.warning('未选中数据~');
            return false;
        }
    JS;
    }
}
