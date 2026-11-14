<?php

namespace App\Admin\Forms;

use App\Jobs\ImportOrder;
use App\Models\OrderLog;
use App\Models\Seller;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Form as AdminForm;
use Dcat\Admin\Form\NestedForm;

class BatchImportOrder2 extends Form
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
        if(!$input["uploads"]) {
            return $this->response()->error('未上传数据');
        }
        
        $inputs = json_decode($input["uploads"], true);
        
        foreach ($inputs as $key => $input) {
            $sellerOpenId = $input['sellerOpenId'];
            if(!$sellerOpenId) {
                return $this->response()->error("第 " . $key + 1 ." 个数据的 店铺未选择");
            }
            $seller = Seller::query()
                ->where('sellerOpenId', $sellerOpenId)
                ->first();
            if(!$seller) {
                return $this->response()->error("第 " . $key + 1 ." 个数据的 店铺不存在，请重新选择");
            }
    
            $file = storage_path('app/public/'.$input['file']);
            
            $log = OrderLog::query()->create([
                "type" => $seller->type,
                "sellerOpenId" => $seller->sellerOpenId,
                "sellerNick" => $seller->sellerNick,
                "file" => $input['file'],
            ]);
    
            // 添加数据导入队列
            ImportOrder::dispatch($file, $log->id);
        }

        return $this
                ->response()
                ->success('导入成功~')
                ->refresh();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $url = url('excel/订单导入模板.xlsx');
        // $wechat_url = url('excel/视频号订单导入模版.xlsx');
        AdminForm::dialog('新增店铺')
            ->click('.create-form')
            ->url('sellers/create')
            ->width('600px')
            ->height('350px')
            ->success('Dcat.reload()');

        $this->html("<div>
        <a href'{$url}' class='btn btn-link' target='_blank' download><i class='fa fa-download' aria-hidden='true'></i> 下载快手导入模板</a>
        <span class='btn btn-success create-form'><i class='fa fa-plus' aria-hidden='true'></i> 新增店铺</span>
    </div>");
    
        $this->array('uploads', '', function (NestedForm $table) {
            $table->select("sellerOpenId")
                ->options(Seller::query()->get()->pluck("sellerNick", "sellerOpenId"))
                ->required();
            $table->file('file', '文件')
                ->accept('xlsx')
                ->required()
                ->uniqueName()
                ->move("imports")
                ->chunked()
                ->maxSize(100 * 1024)
                ->help('只支持 xlsx 格式,请按照模版格式上传以防出错,文件大小请控制在 100MB 以内')
                ->on('startUpload', <<<JS
                        function () {
                            this.uploader.options.formData['_relation'] = 'uploads'
                        }
                JS
                    );
            })->default([
            ["sellerOpenId" => "", "file" => ""]
        ])->saveAsJson();
    }
}
