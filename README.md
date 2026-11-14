# 快手分销管理系统


## 总管理端
/admin

## 达人端
/master

## 定时任务
```
# 更新订单数据 已废弃
php artisan cron:updateOrders

# 每5分钟执行一次
# 订单分佣处理
php artisan create:run-order-commission

# 每天执行一次
# 达人数据统计
php artisan create:run-chart-master

# 每小时执行一次
# 更新达人表中已结算、已提现、可提现佣金字段
php artisan create:update-master-commission

# 每小时执行一次
# 视频号小店订单更新
php artisan app:spider-wechat-order
```

## 队列
```
# 配置队列：数据库
# .env
QUEUE_CONNECTION=database
```

## 配置消息推送
```
# 微信推消息 pushplus token
PUSHPLUS_TOKEN=
```

## 命令
```
# 生成管理端、达人端菜单
php artisan create:menu

# 清空数据
php artisan create:init-system
```

## 环境
- php >= 8.1
- mysql >= 5.7

## supervisor
### centos
1、安装 epel 源
```
yum install -y epel-release
```
2、安装 supervisor
```
yum install -y supervisor
```
3、启动 supervisord 服务
```
systemctl start supervisord
```
4、开机自启动
```
systemctl enable supervisord
```
5、查看 supervisord 服务状态。
```
systemctl status supervisord
```


队列的supervisor配置，参开[文档](https://learnku.com/docs/laravel/10.x/queues/14873#e45763)

*docker中执行队列监听*
```
docker exec -w /www/sites/ks/index/ks -u 1000:1000 1Panel-php8-8ZLi php artisan queue:work --tries=3 --timeout=3600
```
