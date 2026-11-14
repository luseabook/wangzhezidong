<?php

namespace App\Admin\Metrics\Master;

use App\Models\Distributor;
use App\Models\Master;
use App\Models\Order;
use App\Traits\CashTrait;
use Dcat\Admin\Widgets\Metrics\Card;
use Illuminate\Http\Request;

class Total extends Card
{
    use CashTrait;

    protected $master_id = null;

    /**
     * 图表默认高度.
     *
     * @var int
     */
    protected $chartHeight = 350;

    public function __construct($master_id = null) 
    {
        parent::__construct();

        $this->master_id = $master_id;
    }

    protected function init()
    {
        parent::init();

        // 设置标题
        $this->title('总体统计');
        // 设置下拉菜单
        $this->dropdown([
            'all' => '全部',
            'day' => '最近一日',
            'week' => '最近一周',
            'month' => '最近一月',
            'year' => '最近一年',
        ]);
    }

    /**
     * 处理请求.
     *
     * @param Request $request
     *
     * @return void
     */
    public function handle(Request $request)
    {
        $master = Master::query()->find($request->input("master_id"));

        $distributor_ids = Distributor::query()
                ->where("master_id", $master->id)
                ->where("status", 1)
                ->get()
                ->pluck("distributorId")
                ->toArray();
        $model = Order::query()
                    ->whereIn("distributorId", $distributor_ids);
        $parentModel = Order::query();

        switch ($request->get('option')) {
            case 'year':
                $model = $model->where("createTime", ">=", now()->subYear()->startOfDay());
                $parentModel = $parentModel->where("createTime", ">=", now()->subYear()->startOfDay());
                break;
            case 'month':
                $model = $model->where("createTime", ">=", now()->subMonth()->startOfDay());
                $parentModel = $parentModel->where("createTime", ">=", now()->subMonth()->startOfDay());
                break;
            case 'week':
                $model = $model->where("createTime", ">=", now()->subWeek()->startOfDay());
                $parentModel = $parentModel->where("createTime", ">=", now()->subWeek()->startOfDay());
                break;
            case 'day':
                $model = $model->where("createTime", ">=", now()->subDay()->startOfDay());
                $parentModel = $parentModel->where("createTime", ">=", now()->subDay()->startOfDay());
                break;
            case 'all':
            default:
                break;
        }

        $orderTotal = (clone $model)->count();
        $commissionTotal = (clone $model)->ofCommission()->count();
        $refundTotal = (clone $model)->ofRefund()->count();
        $allCommission = $this->allCommissionByModel((clone $model));
        $commissioned = $this->doneCommissionByModel((clone $model));
        $parentCommission = $this->parentCommissionedByModel($parentModel, $master->id);
        $cashed = $this->cashed($master->id);
        $canCommission = bcsub(bcadd($commissioned, $parentCommission, 2), $cashed, 2);
        $refundCommission = $this->refundCommissionByModel((clone $model));

        $childDistributor_ids = [];
        $childMaster = Master::query()
            ->where("parent_id", $master->id)
            ->with(["distributors" => function($query) {
                $query->where("status", 1);
            }])->get();
        foreach ($childMaster as $item) {
            $ids = $item->distributors->pluck("distributorId")->toArray();
            $childDistributor_ids = array_merge($childDistributor_ids, $ids);
        }
        $childCommission = $this->parentCommissioned($childDistributor_ids);
        $content = $this->withContent($orderTotal, $commissionTotal, $refundTotal, $allCommission, $commissioned, $canCommission, $cashed, $refundCommission, $childCommission);
        $this->content($content);
    }

    // 传递自定义参数到 handle 方法
    public function parameters() : array
    {
        return [
            'master_id'        => $this->master_id,
        ];
    }

    /**
     * 渲染卡片内容.
     *
     * @return string
     */
    public function withContent($orderTotal, $commissionTotal, $refundTotal, $allCommission, $commissioned, $canCommission, $cashed, $refundCommission, $childCommission)
    {
        return <<<HTML
<div class="d-flex justify-content-between align-items-center flex-wrap mt-1 px-1" style="margin-bottom: 2px">
<div class="card">
        <div class="card-body">
            <h5 class="card-title">总订单数</h5>
            <div class="card-text mt-2 ml-2 h2">{$orderTotal}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">有效订单数</h5>
            <div class="card-text mt-2 ml-2 h2">{$commissionTotal}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">售后订单数</h5>
            <div class="card-text mt-2 ml-2 h2">{$refundTotal}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">总佣金</h5>
            <div class="card-text mt-2 h2"><i class="fa fa-rmb"></i> {$allCommission}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">已结算佣金</h5>
            <div class="card-text mt-2 h2"><i class="fa fa-rmb"></i> {$commissioned}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">下级抽成佣金</h5>
            <div class="card-text mt-2 h2"><i class="fa fa-rmb"></i> {$childCommission}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">可提现金额</h5>
            <div class="card-text mt-2 h2"><i class="fa fa-rmb"></i> {$canCommission}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">已提现金额</h5>
            <div class="card-text mt-2 h2"><i class="fa fa-rmb"></i> {$cashed}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">售后佣金</h5>
            <div class="card-text mt-2 h2"><i class="fa fa-rmb"></i> {$refundCommission}</div>
        </div>
    </div>
</div>
HTML;
    }
}