<?php

namespace App\Admin\Controllers;

use App\Admin\Forms\BatchImportOrder;
use App\Admin\Forms\BatchImportOrder2;
use App\Models\OrderImport;
use App\Models\OrderLog;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Widgets\Card;
use Dcat\EasyExcel\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderImportController extends AdminController
{
    /**
     * 单文件上传
     */
    public function create(Content $content)
    {
        Admin::style(
            <<<CSS
            .web-uploader .filelist li p.title {
                overflow: visible!important;
                text-overflow: inherit!important;
            }
CSS            
        );

        return $content
            ->translation($this->translation())
            ->title($this->title())
            ->description($this->description()['create'] ?? trans('admin.create'))
            ->body(Card::make(new BatchImportOrder()));
    }

    /**
     * 多文件上传
     */
    public function create2(Content $content)
    {
        Admin::style(
            <<<CSS
            .web-uploader .filelist li p.title {
                overflow: visible!important;
                text-overflow: inherit!important;
            }
CSS            
        );

        return $content
            ->translation($this->translation())
            ->title($this->title())
            ->description($this->description()['create'] ?? trans('admin.create'))
            ->body(Card::make(new BatchImportOrder2()));
    }

    public function export(Request $request)
    {
        $log = OrderLog::query()->find($request->get("id"));

        if(!$log) {
            return $this->response()->error('出错了，请重试~');
        }

        $titles = [
                "订单号",
                "订单创建时间",
                "订单支付时间",
                "预售定金支付时间",
                "订单状态",
                "实付款",
                "快递费",
                "店铺优惠",
                "平台补贴",
                "主播补贴",
                "渠道",
                "支付方式",
                "成交数量",
                "买家留言",
                "账号类型",
                "账号明细",
                "订单备注",
                "旗帜颜色",
                "退货退款",
                "活动订单",
                "预售/承诺发货时间",
                "商品名称",
                "商品ID",
                "商品规格",
                "SKU编码",
                "商品单价",
                "CPS达人ID",
                "CPS达人昵称",
                "预估推广佣金",
                "预估推广者分佣比例",
                "团长ID",
                "团长昵称",
                "快赚客ID",
                "快赚客昵称",
                "授权推广者ID",
                "授权推广者昵称",
                "收货人姓名",
                "收货人电话",
                "收货地址-省",
                "收货地址-市",
                "收货地址-区",
                "收货地址-街道",
                "收货地址",
                "发货时间",
                "快递公司",
                "快递单号",
                "物流信息",
                "实名姓名",
                "身份证号",
                "门店ID",
                "门店名称",
                "门店地址",
                "导入失败信息",
        ];
        $import = new OrderImport();
        $import = $import->setNowTable($log->created_at->format("Y_m"))
            ->where("type", 2)
            ->where("order_log_id", $log->id);

        $file = $log->sellerOpenId.'_'.$log->sellerNick.'_'.$log->created_at->format("Y-m-d-H-i-s").'.xlsx';
        $lastId = 0;
        return Excel::export()
            ->headings($titles) // 设置标题
            ->chunk(function () use (&$lastId, $import) {
                // 每次获取1000条数据导入
                $chunkSize = 1000;

                // 这里使用forPageAfterId批量读取数据，也可以用forPage
                $collection = $import->forPageAfterId($chunkSize, $lastId)->get();

                // 暂存最后一个ID
                $lastId = $collection->last()->id;

                // 当数据库查不到值时会停止执行闭包内的逻辑
                return $collection->map(function($item) {
                    $row = unserialize($item->row);
                    $row[] = $item->messages;

                    return $row;
                });
            })
            ->download($file);
    }
}
