<?php

namespace App\Admin\Imports;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\WithChunkReading;

HeadingRowFormatter::default('none');

class ImportOrder implements ToModel,SkipsEmptyRows,WithHeadingRow,WithValidation,WithMapping,WithChunkReading
{
    use Importable;

    /**
     * @param array $row
     *
     * @return User|null
     */
    public function model(array $row)
    {
        if(!Product::query()->where("itemId", $row[21])->exists()) {
            Product::query()->create([
                "itemId" => $row[21],
                "itemTitle" => $row[20],
                "price" => 100 * str_replace("¥", "", $row[24]),
                'sellerOpenId' => $row[48],
                'sellerNick'   => $row[49],
            ]);
        }

        return Order::updateOrCreate(
            [
                'oid'   => trim($row[0]),
            ],[
                'createTime'   => Carbon::parse($row[1]),
                'updateTime'   => Carbon::parse($row[1]),
                'payTime'      => $row[2] ? Carbon::parse($row[2]) : null,
                'status'       => $this->getStatus($row[4]),
                'totalFee'     => 100 * str_replace("¥", "", $row[5]),
                'expressFee'   => 100 * str_replace("¥", "", $row[6]),
                'discountFee'     => 100 * (str_replace("¥", "", $row[7]) + str_replace("¥", "", $row[8]) + str_replace("¥", "", $row[9])),
                'num' => $row[11],
                'price' => 100 * str_replace("¥", "", $row[24]),
                'refundStatus' => $this->getRefundStatus($row[17]),
                'itemId' => $row[21],
                'distributorId' => $row[26],
                'distributorName' => $row[27],
                'estimatedIncome' => 100 * str_replace("¥", "", $row[28]),
                'commissionRate' => str_replace("%", "", $row[29]),
                'sendTime' => $row[43] ? Carbon::parse($row[43]) : null,
                'sellerOpenId' => $row[48],
                'sellerNick'   => $row[49],
                'orderDetail' => json_encode($row),
            ]
        );
    }

    public function rules(): array
    {
        return [
            '0'  => 'required',
            '1'  => 'required|date',
            '4'  => 'required',
            '5'  => 'required',
            '6'  => 'required',
            '7'  => 'required',
            '8'  => 'required',
            '9'  => 'required',
            '12'  => 'required|numeric',
            '21'  => 'required',
            '22'  => 'required',
            '25'  => 'required',
            '26'  => 'required',
            '27'  => 'required',
            '28'  => 'required',
            '29'  => 'required',
        ];
    }

    public function headingRow(): int
    {
        return 1;
    }

    
    public function map($row): array
    {
        return array_values($row);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * 获取订单状态
     *
     * @param string $status
     * @return int
     */
    private function getStatus($status): int
    {
        $map = [
            "交易关闭" => 80,
            "待付款"   => 10,
            "已付款" => 30,
            "待发货" => 30,
            "已发货" => 40,
            "已收货" => 50,
            "已签收" => 50,
            "订单成功" => 70,
            "交易成功" => 70,
            "订单失败" => 80,
        ];

        return isset($map[$status]) ? $map[$status] : 0;
    }

    /**
     * 获取订单退款状态
     *
     * @param string $status
     * @return int
     */
    private function getRefundStatus($status): int
    {
        $map = [
            "买家仅退款申请" => 10,
            "买家退货退款申请"   => 11,
            "平台介入-买家仅退款申请" => 20,
            "平台介入-买家退货退款申请" => 21,
            "平台介入-已确认退货退款" => 22,
            "商品回寄信息待买家更新" => 30,
            "商品回寄信息待卖家确认" => 40,
            "待买家确认收货" => 45,
            "退款执行中" => 50,
            "退款成功" => 60,
            "退款失败" => 70,
            "退款关闭" => 70,
            "待卖家收货" => 40,
            "待买家处理" => 80,
            "待买家退货" => 85,
            "待卖家处理" => 90,
            "待平台判定" => 25,
        ];

        return ($status && isset($map[$status])) ? $map[$status] : 0;
    }
}