<?php

namespace App\Master\Actions\Grid;

use App\Master\Forms\CashEdit;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;

class EditCash extends RowAction
{
    protected $title = '修改金额';

    public function render()
    {
        $form = CashEdit::make()->payload(['id' => $this->getKey()]);

        return Modal::make()
            ->lg()
            ->title($this->title)
            ->body($form)
            ->button($this->title);
    }
}