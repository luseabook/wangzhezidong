<?php

namespace App\Admin\Exports;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use Dcat\Admin\Grid\Exporters\AbstractExporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class OrderExport extends AbstractExporter implements ShouldAutoSize, WithMapping, WithHeadings, FromCollection
{
    use Exportable;


    private $rates;

    public function __construct()
    {
        parent::__construct();

        $this->getMasters();
    }

    public function getMasters()
    {
        Distributor::query()
            ->with('master')
            ->get()
            ->each(function($item) {
                if($item->master && $item->master->rate1) {
                    $this->rates[$item->distributorId] = $item->master->rate1;
                }
            });
    }


    public function export()
    {
        $file = "订单列表-" . date("Y-m-d-H:i:s") . ".xlsx";
        return $this->download($file)
                    ->prepare(request())
                    ->send();
    }

    public function collection()
    {
        return collect($this->buildData());
    }

    public function headings(): array
    {
        return [
            "id"       => "ID",
            "oid"      => "订单ID",
            "payTime"  => "付款时间",
            "sendTime" => "发货时间",
            "status"   => "订单状态",
            "sellerNick"   => "店铺名称",
            "distributorId"   => "达人ID",
            "distributorName" => "达人昵称",
            "itemId"     => "商品ID",
            "itemTitle"  => "商品名称",
            "price"      => "商品单价",
            "num"        => "商品数量",
            "totalFee"   => "订单金额(实付款)",
            "commissionRate" => "分销率(官方佣金率1%)",
            "cashPrice"  => "预估佣金",
            "is_rate"    => "是否分佣",
            "rate1Price" => "第一次分佣金额",
            "rate2Price" => "第二次分佣金额",
            "rate"       => "快手首次结算比例", 
            "cashStatus" => "结算状态",
            "created_at" => "创建时间",
            "updated_at" => "更新时间",
        ];
    }

    public function map($row): array
    {
        return [
            $row["id"],
            $row["oid"] . " ",
            $row["payTime"],
            $row["sendTime"],
            $this->getStatus($row["status"]),
            $row['sellerNick'],
            $row["distributorId"],
            ltrim($row["distributorName"], "="),
            $row["itemId"] . " ",
            isset($row["product.itemTitle"]) ? $row["product.itemTitle"] : "",
            isset($row["product.price"]) && $row["product.price"] ? $row["product.price"] / 100 : "",
            $row["num"],
            $row["totalFee"] / 100,
            isset($row["product.commissionRate"]) && $row["product.commissionRate"] ? ($row["product.commissionRate"] - 1) . "%" : "0%",
            $row["commissionTotal"] / 100,
            $row["is_rate"] ? "是" : "否",
            $row["commission1"] / 100,
            $row["commission2"] / 100,
            isset($this->rates[$row["distributorId"]]) ? $this->rates[$row["distributorId"]] . "%" : "",
            $row["cash_status"],
            $row["created_at"],
            $row["updated_at"],
        ];
    }

    private function getStatus($status)
    {
        return isset(Order::$status[$status]) ? Order::$status[$status] : "";
    }
}