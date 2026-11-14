<div class="card">
    <div class="card-header">
        <h4 style="margin:0">待登记达人</h4>
        <div style="float:right;margin-top:-30px">
            <form method="get" style="margin:0">
                <select name="days" onchange="this.form.submit()" class="form-control" style="width:auto;display:inline-block">
                    @foreach([7, 30, 90, 365] as $d)
                        <option value="{{ $d }}" @if($days == $d) selected @endif>近 {{ $d }} 天</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div style="padding:12px 15px;background:#fff9db;color:#9c6500;border-bottom:1px solid #f5f0d0">
            共 <b>{{ $rows->count() }}</b> 个达人号未登记，涉及 <b>{{ number_format($affect) }}</b> 笔订单、
            金额 <b>¥{{ number_format($gmv / 100, 2) }}</b>。
            这些订单不会产生佣金 —— 去「达人管理 → 分销者」给他们建一条记录（选好所属达人并启用）即可。
        </div>

        @if($rows->isEmpty())
            <div style="padding:30px;text-align:center;color:#999">近期订单里的达人都已登记，没有遗漏</div>
        @else
        <table class="table table-hover" style="margin:0">
            <thead>
                <tr style="background:#fafafa">
                    <th style="width:60px">#</th>
                    <th>达人号</th>
                    <th>达人昵称</th>
                    <th style="width:100px">订单数</th>
                    <th style="width:140px">订单金额</th>
                    <th style="width:170px">最早订单</th>
                    <th style="width:170px">最近订单</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $i => $r)
                <tr>
                    <td style="color:#999">{{ $i + 1 }}</td>
                    <td><code style="color:#c92a2a">{{ $r->distributorId }}</code></td>
                    <td>{{ $r->distributorName ?: '—' }}</td>
                    <td>{{ number_format($r->orders_cnt) }}</td>
                    <td>¥{{ number_format($r->gmv_cents / 100, 2) }}</td>
                    <td style="color:#888">{{ $r->first_at }}</td>
                    <td style="color:#888">{{ $r->last_at }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>