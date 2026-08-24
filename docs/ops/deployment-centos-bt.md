# 运维与部署文档：CentOS + 宝塔

## 1. 目标环境

项目第一阶段采用单服务器部署，适合低成本上线和新手维护。

推荐环境：

| 项 | 建议 |
|---|---|
| 系统 | CentOS Stream 9 / AlmaLinux / RockyLinux，若云厂商镜像限制可用 CentOS 兼容发行版 |
| 面板 | 宝塔 Linux 面板 |
| Web | Nginx |
| PHP | PHP 8.3 或 8.4 |
| Node.js | Node.js LTS，用于构建 Inertia/Vue 前台资源 |
| 数据库 | MySQL 8.0 |
| 缓存/队列 | Redis |
| 进程守护 | Supervisor |
| SSL | Let's Encrypt 或云厂商证书 |
| 文件存储 | 腾讯云 COS，不把大量图片放服务器本地 |

官方文档：

- 宝塔快速安装：https://docs.bt.cn/getting-started/quick-installation-of-bt-panel
- 宝塔基础环境：https://docs.bt.cn/getting-started/install-basic-environment
- 宝塔官方文档：https://docs.bt.cn/
- CentOS 文档：https://docs.centos.org/

## 2. 上线前准备

1. 域名完成备案。
2. 域名解析到服务器公网 IP。
3. 云服务器安全组放行：80、443、宝塔面板端口、SSH 端口。
4. 创建腾讯云 COS Bucket。
5. 开通数据万象并绑定 Bucket。
6. 准备 `.env` 中需要的密钥。
7. 微信支付、短信等未开通时用 mock 或关闭入口。

## 3. 宝塔安装步骤

1. 使用 SSH 登录纯净服务器。
2. 按宝塔官方文档安装面板。
3. 登录面板，修改默认账号、密码和面板端口。
4. 安装 LNMP：Nginx、MySQL、PHP、phpMyAdmin。
5. 安装 Redis。
6. 安装 Supervisor 或使用宝塔进程守护插件。
7. 创建站点并绑定域名。
8. 配置 SSL。

## 4. Laravel 部署步骤

1. 上传代码或通过 Git 拉取代码。
2. 设置站点根目录指向 Laravel 的 `public/`。
3. 安装 Composer 依赖：

```bash
composer install --no-dev --optimize-autoloader
```

4. 安装前端依赖并构建：

```bash
npm ci
npm run build
```

5. 创建 `.env`，配置数据库、Redis、COS、支付等。
6. 生成应用密钥：

```bash
php artisan key:generate
```

7. 执行迁移：

```bash
php artisan migrate --force
```

8. 缓存配置：

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

9. 启动队列：

```bash
php artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3
```

10. 配置 Supervisor 守护队列。

如果启用 Inertia SSR，还需要构建 SSR 包并用 Supervisor 守护 SSR 进程：

```bash
npm run build:ssr
```

SSR 不是 P0 必须项。P0 可以先关闭 SSR，上线后根据 SEO 和首屏性能再开启。

Laravel 队列参考：https://laravel.com/framework/docs/12.x/queues

## 5. 备份策略

### 数据库

- 每日自动备份 MySQL。
- 保留最近 7 天每日备份、最近 4 周每周备份、最近 6 个月每月备份。
- 每周至少一次下载备份到本地或对象存储。

### 图片

- 图片原文件在 COS，开启版本控制或生命周期策略需谨慎。
- 误删恢复策略上线前必须演练一次。
- 数据库中保存 COS Key，不依赖本地文件路径。

### 配置

- `.env` 不进 Git，但要有加密备份。
- 微信支付证书、私钥单独备份，限制读取权限。

## 6. 监控与日志

- Laravel 日志保留至少 14 天。
- 支付回调日志单独记录。
- 队列失败任务必须可在后台查看。
- COS/数据万象调用失败要记录错误码。
- 磁盘使用率超过 80% 必须告警。

## 7. 发布流程

每次上线：

1. 备份数据库。
2. 拉取代码。
3. 安装依赖。
4. 构建前端。
5. 进入维护模式。
6. 运行迁移。
7. 清理并重建缓存。
8. 重启 PHP-FPM。
9. 重启队列。
10. 退出维护模式。
11. 手动检查首页、搜索页、后台、上传、登录。
