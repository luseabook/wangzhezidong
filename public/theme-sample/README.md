# UI 样张 v2

墨蓝冷灰 · 克制金融感换皮样张。所有资源使用相对路径，直接打开即可。

查看方式（任选其一）：

1. 双击 `open-sample.cmd`
2. 直接双击 `index.html`
3. 双击 `start-server.cmd`，然后打开 http://127.0.0.1:4321/theme-sample/index.html

两套皮肤：

- 墨蓝冷灰 v2：`index.html`，或双击 `open-sample.cmd`
- 黑白非线性 MONO：`mono.html`，或双击 `open-mono.cmd`
- 任意页面加 `?theme=mono` 即可切换；页面右下角角标也能来回切

页面：

- login.html 管理端登录
- dashboard.html 管理端数据面板
- orders.html 订单列表
- form.html 编辑达人 + 弹窗
- master.html 达人端首页

主题 CSS：`public/vendor/theme/tokens.css`、`admin.css`、`master.css`。
正式接入时只需在两端 bootstrap 各加两行 `Admin::css()`，不改页面结构。