# 授权中心（独立服务）

零依赖 Node 服务（`node:http` + `node:sqlite`），与任何客户的结算系统解耦。

## 运行

```bash
PORT=8899 \
ADMIN_PASSWORD=改成强密码 \
DB_PATH=./data/license.db \
BASE_PATH=            # 若挂在反向代理前缀下（如 /lic）则填 /lic
MAX_SWITCH_PER_DAY=10 \
SWITCH_COOLDOWN_MIN=10 \
node server.mjs
```

后台：`http://127.0.0.1:8899/admin`

## 接口

| 方法 | 路径 | 用途 |
|---|---|---|
| POST | /api/activate | 激活码换 地址/令牌/店铺位 |
| POST | /api/bind | 绑店（店铺位/冷却/每日上限校验） |
| POST | /api/unbind | 解绑腾位 |
| POST | /api/heartbeat | 客户端心跳 |
| POST | /api/report-shops | 结算系统回传实际使用的店铺 |
| GET  | /api/license | 授权状态查询 |

## 测试

```bash
node test/run.mjs      http://127.0.0.1:8899 <密码>    # 24 项
node test/limits.mjs   http://127.0.0.1:8903 <密码> COOLDOWN
node test/limits.mjs   http://127.0.0.1:8902 <密码> DAILY_LIMIT
```

## 计费口径（B 方案）

- `slots` = 同时可占用的店铺位；换店不额外收费
- 解绑立即腾位；解绑计入「换店次数」，受每日上限与冷却限制
- 超配、未授权店铺、多设备、反复超位尝试 → 后台告警（不做外部推送）

## 为什么破解客户端也发现得了

结算系统在每次导入后回传所用 `shop_id`（服务器到服务器，客户端不参与）。
授权中心据此登记真实占用并比对授权位，超出即产生 high 告警。
