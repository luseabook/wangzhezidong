<?php

namespace App\Admin\Forms;

use App\Admin\Imports\ImportOrder as ImportsImportOrder;
use Dcat\Admin\Widgets\Form;
use Maatwebsite\Excel\Facades\Excel;

class ImportOrder extends Form
{
    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return mixed
     */
    public function handle(array $input)
    {
        $file = storage_path('app/public/'.$input['file']);
        Excel::import(new ImportsImportOrder(), $file);

        return $this
				->response()
				->alert(true)
                ->success('导入成功')
                ->detail($file);;
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $url = url('excel/订单导入模板.xlsx');
        $this->html('<a href="'.$url.'" class="btn btn-link" target="_blank" download><i class="fa fa-download" aria-hidden="true"></i> 下载导入模板</a>');

        $this->file('file', '文件')
            ->accept('xlsx')
            ->required()
            ->uniqueName()
            ->move("imports")
            ->chunked()
            ->maxSize(50 * 1024)
            ->help('只支持 xlsx 格式,请按照模版格式上传以防出错');
    }
}
