# 快手分销管理系统

Laravel 10 + dcat-admin 的双端分销结算系统，配套 Electron 自动同步客户端和独立授权中心。

## 组成

| 模块 | 路径 | 说明 |
|---|---|---|
| 运营后台 | `/admin` | 订单、达人、分销者、商品、结算、提现审核、统计 |
| 达人端 | `/master` | 订单、佣金、提现、下级与团长分佣 |
| 同步客户端 | `client/` | Electron 托盘程序：驱动 Chrome 拉单、产 xlsx、上传结算系统 |
| 授权中心 | `license-center/` | 独立服务：激活、店铺授权、在线更新与健康巡检 |
| 结算业务文档 | [docs/结算系统业务逻辑梳理.md](docs/结算系统业务逻辑梳理.md) | 完整结算规则、状态机、问题修复记录 |

## 环境

- PHP >= 8.1
- MySQL >= 5.7
- Node.js >= 18（仅客户端/前端工具需要）
- 队列：`QUEUE_CONNECTION=database`
- 并发锁：单机可用 `file`/`database`/`redis`；多机部署请使用共享缓存（Redis/DB）

## 安装与升级

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan create:menu
php artisan queue:restart
```

2026-11 新增的迁移：

- `2026_11_15_000000_add_is_leader_of_money_table.php`：区分团长分佣与上级抽成
- `2026_11_16_000000_add_settlement_dirty_of_orders_table.php`：订单金额/商品/归属变更待重算
- `2026_11_16_010000_change_cash_money_type_of_masters_table.php`：`cash_money` 改为 decimal 元

升级前请先备份数据库；订单量较大时，`settlement_dirty` 迁移建议低峰执行。

## 定时任务

以 `app/Console/Kernel.php` 为准：

| 频率 | 命令 | 作用 |
|---|---|---|
| 每 30 分钟 | `create:run-order-commission` | 订单分佣/售后/关闭扣回 |
| 每小时 | `create:update-master-commission` | 刷新达人已结算/已提现/可提现缓存 |
| 每天 01:00 | `create:run-chart-master` | 达人/达人号日统计 |
| 每天 02:00 | `create:order-statistics` | 全局佣金日汇总 |
| 每天 03:00 | `create:run-count` | 重算近 30 天达人日统计 |
| 每 10 分钟 | `client:health-check` | 客户端/队列健康巡检 |

> 视频号 `app:spider-wechat-order` 已停用；快手订单由 `client/` 同步。
> `cron:updateOrders` 为旧的平台接口同步命令，未加入调度。

## 结算要点

- 两阶段结算，等待期按**发货时间**起算：首笔 `sendTime + commission1_days`（默认 72h），尾笔在订单成功后再满足 `sendTime + commission2_days`（默认 600h）。
- 分佣比例：官方分佣率 − 平台 1 个百分点 − 团长比例；上级抽成按订单净分佣率封顶。
- 售后、订单关闭（status 0/80）只对**已结算**订单按账本净额扣回；关闭订单重新有效会自动重结。
- 提现确认前会按当前可提现额复核，不足则整批不确认。
- 商品改率、订单金额/归属变化会触发冲正 + 重算。
- 结算命令带 `Cache` 锁，定时任务/商品改率 Job 不会重叠。

完整规则、状态机、公式与修复记录见 [docs/结算系统业务逻辑梳理.md](docs/结算系统业务逻辑梳理.md)。

## 同步客户端

源码在 `client/`，发布/签名/在线更新见 [client/RELEASE.md](client/RELEASE.md)。

- 默认 `refresh_window_days=0`：从 `history_start_date`（默认 `2023-11-01`）全量回刷历史，不再限制最近 45 天。
- 每天 `full_refresh_hour`（默认 3 点）之后做一次全量；托盘菜单「全量回刷历史」可手动触发。
- 旧版 `refresh_window_days=45` 配置会自动迁移到新配置（`config_version=2`）。
- 全量首轮耗时较长，建议先手动跑一次并观察平台风控。

构建：

```bash
cd client
npm ci
npm run dist
```

客户端默认从授权中心下发的 `CLIENT_UPDATE_URL` 检查更新；具体发布流程见 `client/RELEASE.md`。

## 队列与 supervisor

队列使用数据库驱动：

```bash
php artisan queue:work --tries=3 --timeout=3600
```

Docker 示例：

```bash
docker exec -w /www/sites/ks/index/ks -u 1000:1000 1Panel-php8-8ZLi php artisan queue:work --tries=3 --timeout=3600
```

Supervisor 配置参考 [Laravel 10 队列文档](https://learnku.com/docs/laravel/10.x/queues/14873#e45763)。

## 环境变量

```ini
APP_ENV=production
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
PUSHPLUS_TOKEN=
```

订单导入通知使用 `PUSHPLUS_TOKEN`（可选）。