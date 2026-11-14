# 客户端发布与在线更新

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

## 6. 发布验证清单

1. 安装旧版本 `1.0.0`
2. 上传新版本 `1.0.1`、`latest.yml`、`blockmap`
3. 启动旧客户端，确认面板显示“发现新版本”
4. 确认下载进度和“重启安装”
5. 重启后版本变为 `1.0.1`，店铺、授权、订单数据仍在
6. 关闭自动更新源，确认业务同步不受影响
7. 在测试机验证 `beta` 通道
8. 保留旧安装包；需要回滚时手工安装旧版本

> `electron-updater` 默认不降级。回滚时保留旧安装包并手工重装，或者发布一个版本号更高的回滚版本。

## 7. 客户端健康巡检

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
