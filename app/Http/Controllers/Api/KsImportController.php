<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ImportOrder;
use App\Models\OrderLog;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 快手订单自动同步的接收端点。
 *
 * 设计原则：只做「接收文件 + 建导入记录 + 派发队列」，
 * 解析、校验、失败导出、通知全部复用既有的 ImportOrder / DealImportOrder，
 * 不改动任何既有导入逻辑。
 */
class KsImportController extends Controller
{
    /**
     * POST /api/ks/import
     * multipart: shop_id, xlsx
     */
    public function store(Request $request)
    {
        $request->validate([
            'shop_id' => 'required|string|max:64',
            'xlsx'    => 'required|file|mimes:xlsx|max:102400',
        ]);

        $seller = Seller::query()->where('sellerOpenId', $request->input('shop_id'))->first();
        if (!$seller) {
            return response()->json([
                'ok'    => false,
                'error' => 'UNKNOWN_SHOP_ID',
                'shop_id' => $request->input('shop_id'),
            ], 422);
        }

        // 必须落在 public 磁盘的 imports 目录：
        // 与手工上传(dcat file 字段 ->move('imports'))同目录，
        // 且 ImportOrder Job 读取的是 storage_path('app/public/'.$path)。
        $path = $request->file('xlsx')->store('imports', 'public');

        $log = OrderLog::query()->create([
            'type'         => $seller->type,
            'sellerOpenId' => $seller->sellerOpenId,
            'sellerNick'   => $seller->sellerNick,
            'file'         => $path,
            'source'       => 1, // 1=自动
        ]);

        ImportOrder::dispatch(storage_path('app/public/' . $path), $log->id);

        // 向授权中心回传"本系统正在使用哪个店铺"。
        // 这是「破解客户端也能发现超配」的关键链路：由结算系统服务器到服务器上报，客户端不参与。
        $this->reportToLicenseCenter($seller->sellerOpenId);

        Log::info('[ks-import] accepted', [
            'log_id'  => $log->id,
            'shop_id' => $seller->sellerOpenId,
            'file'    => $path,
            'bytes'   => $request->file('xlsx')->getSize(),
        ]);

        return response()->json([
            'ok'     => true,
            'log_id' => $log->id,
            'file'   => $path,
        ]);
    }

    /**
     * 上报到授权中心。失败绝不影响导入，只记日志。
     */
    private function reportToLicenseCenter(string $shopId): void
    {
        $base = rtrim((string) config('app.ks_license_url'), '/');
        $code = (string) config('app.ks_client_code');

        if ($base === '' || $code === '') {
            return;   // 未接入授权中心（例如本地自测），静默跳过
        }

        try {
            Http::timeout(2)->connectTimeout(1)->post($base . '/api/report-shops', [
                'client_code' => $code,
                'shop_ids'    => [$shopId],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ks-import] 授权中心上报失败（不影响导入）', [
                'shop' => $shopId,
                'err'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/ks/shop/{id}
     * 客户端绑定后用它取店铺显示名，并顺带校验该店铺是否已在结算系统登记。
     */
    public function shop(string $id)
    {
        $seller = Seller::query()->where('sellerOpenId', $id)->first();
        if (!$seller) {
            // 用 200 + ok:false，避免客户端把路由级 404 页面误当网络异常
            return response()->json([
                'ok'             => false,
                'error'          => 'SHOP_NOT_REGISTERED',
                'seller_open_id' => $id,
            ]);
        }

        return response()->json([
            'ok'             => true,
            'seller_open_id' => $seller->sellerOpenId,
            'seller_nick'    => $seller->sellerNick,
            'type'           => $seller->type,
        ]);
    }

    /**
     * GET /api/ks/import/{id}
     * 查询导入进度与结果
     */
    public function show(int $id)
    {
        $log = OrderLog::query()->find($id);
        if (!$log) {
            return response()->json(['ok' => false, 'error' => 'NOT_FOUND'], 404);
        }

        return response()->json([
            'ok'           => true,
            'log_id'       => $log->id,
            'status'       => $log->status,     // 4导入中 5已导入 9处理中 10已处理 11处理失败
            'total'        => $log->total,
            'success'      => $log->success,
            'fail'         => $log->fail,
            'unPayOrCps'   => $log->unPayOrCps,
            'unSend'       => $log->unSend,
            'fail_file'    => $log->failFile,
            'source'       => $log->source ?? null,
            'finished'     => in_array((int) $log->status, [10, 11], true),
        ]);
    }
}