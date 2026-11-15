# 客户端发布与在线更新

> 2026-11-16：客户端已取消 45 天同步窗口，默认从 `history_start_date` 全量回刷。旧配置会自动迁移（`config_version=2`）。
> 发布新版前请把 `package.json` 的 version 从 `1.0.0` 提到 `1.0.1`（或更高），重新 `npm run dist` 并上传安装包、`latest.yml`、`.blockmap`。
> 全量首轮耗时较长，可在托盘菜单「全量回刷历史」手动跑一次，观察平台风控与耗时。

## 1. 前置条件

- Windows 代码签名证书（OV/EV）
- 支持 HTTPS 的更新文件存储：OSS / COS / S3 / CDN
- 一个固定更新目录，例如：

```text
https://updates.example.com/ks-sync/
├── latest.yml
├── KsSync-Setup-1.0.1.exe
└── KsSync-Setup-1.0.1.exe.blockmap
```

> 没有代码签名证书时，不要启用静默自动安装。可以先只发布“检查更新 + 下载链接”，证书到位后再开启自动安装。

## 2. 构建

先修改 `package.json` 的 `version`，例如 `1.0.1`。

```powershell
cd client
npm ci
$env:CSC_LINK = "C:\path\codesign.pfx"
$env:CSC_KEY_PASSWORD = "证书密码"
npm run dist
```

产物：

```text
dist/KsSync-Setup-1.0.1.exe
dist/KsSync-Setup-1.0.1.exe.blockmap
dist/latest.yml
```

把这三个文件上传到更新目录。`latest.yml` 必须和安装包在同一路径下。

`build.publish` 里的 `https://updates.example.com/ks-sync` 是打包时的占位地址；实际运行时由授权中心下发的 `CLIENT_UPDATE_URL` 覆盖，不需要把真实地址硬编码在安装包里。

## 3. 授权中心下发更新源

在 `/etc/systemd/system/ks-license.service` 增加：

```ini
Environment=CLIENT_UPDATE_URL=https://updates.example.com/ks-sync
Environment=CLIENT_UPDATE_CHANNEL=latest
```

然后：

```bash
systemctl daemon-reload
systemctl restart ks-license
```

客户端激活接口会返回：

```json
{
  "update_feed": "https://updates.example.com/ks-sync",
  "update_channel": "latest"
}
```

已激活的老客户端如果没有 `update.feed_url`，需要重新激活，或手工在 `%APPDATA%\KsSync\config.json` 填入：

```json
{
  "update": {
    "feed_url": "https://updates.example.com/ks-sync",
    "channel": "latest",
    "check_interval_hours": 4,
    "auto_download": true,
    "allow_prerelease": false
  }
}
```

## 4. 更新行为

- 启动 15 秒后检查一次
- 之后每 `check_interval_hours` 小时检查一次
- 默认后台下载
- 下载完成后面板显示“重启安装”
- 退出时自动安装
- 检查失败、下载失败不会影响订单同步
- 更新源未配置时面板显示“更新源未配置”，不会阻塞业务

更新通道：

- `latest`：稳定版
- `beta`：灰度版

测试时先把少量客户端的 `update.channel` 改为 `beta`，验证通过后再切回 `latest`。

## 5. 开机自启

- 安装后默认注册开机自启，启动参数为 `--hidden`
- 开机启动只进入托盘，不弹面板
- 面板底部可关闭/开启“开机自启”
- 开发模式只保存配置，不写 Windows 登录项

## 6. 测试阶段：自签名证书

当前项目和少量测试用户可以先使用自签名证书，不采购正式证书。

- 公钥证书：`client/build/wangzhe-test-codesign.cer`
- 私钥 PFX：`E:\wangzhe\wangzhe_project_b\certs\wangzhe-test-codesign.pfx`
- PFX 密码：`E:\wangzhe\certs\password.txt`
- 证书主题：`CN=Wangzhe Test`
- `publisherName`：`Wangzhe Test`

私钥 PFX 和密码文件不要提交、不要发到群里；公钥 `.cer` 可以随安装包发给测试用户。

构建：

```powershell
cd client
$env:CSC_LINK = "E:\wangzhe\wangzhe_project_b\certs\wangzhe-test-codesign.pfx"
$env:CSC_KEY_PASSWORD = (Get-Content "E:\wangzhe\certs\password.txt" -Raw).Trim()
npm run dist
```

测试用户第一次安装前，先运行：

```powershell
powershell -ExecutionPolicy Bypass -File .\build\install-test-cert.ps1
```

或双击 `build\wangzhe-test-codesign.cer`，安装到：

- 受信任的根证书颁发机构
- 受信任的发布者

安装证书后再打开安装包。未安装证书时，Windows 仍会提示未知发布者，`Get-AuthenticodeSignature` 也会返回 `UnknownError`；这是自签名证书的正常状态。

自签名证书只适合少量测试用户和内部升级验证。正式对外交付、尤其是开启静默自动更新时，仍建议换成 OV/EV 证书。

## 7. 发布验证清单

1. 安装旧版本 `1.0.0`
2. 上传新版本 `1.0.1`、`latest.yml`、`blockmap`
3. 启动旧客户端，确认面板显示“发现新版本”
4. 确认下载进度和“重启安装”
5. 重启后版本变为 `1.0.1`，店铺、授权、订单数据仍在
6. 关闭自动更新源，确认业务同步不受影响
7. 在测试机验证 `beta` 通道
8. 保留旧安装包；需要回滚时手工安装旧版本

> `electron-updater` 默认不降级。回滚时保留旧安装包并手工重装，或者发布一个版本号更高的回滚版本。

## 9. 全量历史回刷（2026-11-16）

客户端不再只回刷最近 45 天，改为按历史起点全量回刷：

- `refresh_window_days=0`：不限窗口，从 `history_start_date` 拉到现在。
- `history_start_date` 默认 `2023-11-01`，可在 `%APPDATA%\KsSync\config.json` 调整。
- `max_days_per_run` 默认 4000 天（日期切片上限）。
- `full_refresh_hour` 默认 3：每天该小时之后做一次全量回刷。
- `full_refresh_interval_days` 默认 1：全量回刷间隔；改为 7 即每周一次。
- 旧版 `refresh_window_days=45` 的配置会自动迁移到新配置，`config_version` 变为 2。
- 托盘菜单新增「全量回刷历史」，可手动触发一轮全量。

注意：

- 全量首轮会请求从历史起点到今天的全部日期，耗时长、请求量远高于 45 天窗口；建议先手动跑一次，确认平台风控与耗时后再交给定时任务。
- 全量期间客户端运行锁会保持，重复启动/多开会被单实例锁拦住。
- 上传仍只针对有变动的日期，不会重复上传未变数据。

## 8. 客户端健康巡检

授权中心：

```bash
DB_PATH=/www/server/ks-license/data/license.db \
MIN_CLIENT_VERSION=1.0.0 \
HEALTH_WEBHOOK=https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=... \
node /www/server/ks-license/tools/healthcheck.mjs
```

建议 cron 每 10 分钟：

```cron
*/10 * * * * root DB_PATH=/www/server/ks-license/data/license.db MIN_CLIENT_VERSION=1.0.0 HEALTH_WEBHOOK=... /usr/bin/node /www/server/ks-license/tools/healthcheck.mjs >> /var/log/ks-license-health.log 2>&1
```

结算系统侧：

```bash
cd /www/wwwroot/ks
sudo -u www php artisan client:health-check --dry-run
```

检查：

- 最近自动导入是否超过 90 分钟
- `failed_jobs` 是否有失败任务
- 最近 24 小时是否有处理失败的导入
- 长时间未完成的导入

配置 webhook 后由 cron 每 10 分钟执行一次，建议每租户分别执行。
