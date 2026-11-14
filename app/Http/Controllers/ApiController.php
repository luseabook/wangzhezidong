<?php

namespace App\Http\Controllers;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function master(Request $request)
    {
        $date = Carbon::parse($request->input("date"));
        $list = Master::query()
            ->select("id", "username")
            ->with(["distributors" => function($query) {
                $query->where("status", 1);
            }])
            ->whereHas("distributors", function($query) {
                $query->where("status", 1);
            })
            ->orderBy("id", "asc")
            ->paginate(100);

        $list->getCollection()
            ->transform(function($item) use ($date) {
                $distributors = $item->distributors;
                // 当日订单总数
                $distributor_ids = (clone $distributors)->pluck("distributorId")->toArray();
                $item->order = Order::query()
                    ->whereIn("distributorId", $distributor_ids)
                    ->where("payTime", ">=", $date->startOfDay()->format("Y-m-d H:i:s"))
                    ->where("payTime", "<=", $date->endOfDay()->format("Y-m-d H:i:s"))
                    ->whereIn("refundStatus", [0, 70])
                    ->whereIn("status", [30, 40, 50, 70])
                    ->count();

                // 当日新增达人
                $item->total = $distributors->where("created_at", ">=", $date->startOfDay()->format("Y-m-d H:i:s"))
                    ->where("created_at", "<=", $date->endOfDay()->format("Y-m-d H:i:s"))
                    ->count();
                unset($item->distributors);
                return $item;
            })->all();

        return $list;
    }

    public function distributor(Request $request)
    {
        $date = Carbon::parse($request->input("date"));
        
        $list = Distributor::query()
            ->select("id", "distributorId", "distributorName", "master_id")
            ->where("status", 1)
            ->orderBy("id", "asc")
            ->paginate(100);

        $list->getCollection()
            ->transform(function($item) use ($date) {
                // 当日订单总数
                $model = Order::query()
                    ->where("distributorId", $item->distributorId)
                    ->where("payTime", ">=", $date->startOfDay()->format("Y-m-d H:i:s"))
                    ->where("payTime", "<=", $date->endOfDay()->format("Y-m-d H:i:s"))
                    ->whereIn("refundStatus", [0, 70])
                    ->whereIn("status", [30, 40, 50, 70]);

                $item->order = (clone $model)->count();

                // 当日成交总金额
                $item->total = $model->sum("totalFee");

                return $item;
            })->all();

        return $list;
    }

    public function product(Request $request)
    {
        $date = Carbon::parse($request->input("date"));
        
        return DB::select("SELECT itemId, count(*) as total FROM `orders` where payTime >=? and payTime <= ? group by itemId", [
            $date->startOfDay()->format("Y-m-d H:i:s"),
            $date->endOfDay()->format("Y-m-d H:i:s")
        ]);
        // return Order::query()
        //     ->selectRaw("itemId, count(*) as total")
        //     ->where("payTime", ">=", $date->startOfDay()->format("Y-m-d H:i:s"))
        //     ->where("payTime", "<=", $date->endOfDay()->format("Y-m-d H:i:s"))
        //     ->groupBy("itemId")
        //     ->get();
    }
}
