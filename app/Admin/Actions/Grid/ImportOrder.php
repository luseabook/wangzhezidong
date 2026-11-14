<?php

namespace App\Admin\Actions\Grid;

use App\Admin\Forms\ImportOrder as FormsImportOrder;
use Dcat\Admin\Grid\Tools\AbstractTool;
use Dcat\Admin\Widgets\Modal;

class ImportOrder extends AbstractTool
{
    /**
     * @return string
     */
	protected $title = '导入订单';

    /**
     * @return string
     */
    protected function button()
    {
        return '<button class="btn bg-success waves-effect"><i class="fa fa-upload" aria-hidden="true"></i> '.$this->title.'</button>';
    }

    public function render()
    {
        $form = FormsImportOrder::make();

        return Modal::make()
            ->lg()
            ->title($this->title)
            ->body($form)
            ->button($this->button());
    }
}
