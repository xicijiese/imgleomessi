# 运维与部署文档：Ubuntu 24.04 + 宝塔（历史文件名保留）

本文档是本项目进入真实生产环境前的准备、部署、验收、备份和回滚手册。

本项目采用 Laravel 单体应用：Nginx 提供 Web 服务，PHP-FPM 运行 Laravel，MySQL 保存业务数据，Redis 提供缓存/会话/队列，Supervisor 守护 Laravel 队列 Worker，腾讯云 COS / 数据万象负责生产图片存储和图片处理。

本文档只描述生产部署方案，不代表已经连接或修改真实 VPS。所有带有“确认后执行”的命令，都必须在备份、维护窗口和回滚方案准备完成后执行。

## 0. 当前生产部署阻塞项

在执行真实生产部署前，必须先完成以下代码和账号安全确认：

1. 当前 `app/Models/User.php` 的 Filament `canAccessPanel()` 只允许 `local/testing` 环境。必须先补齐生产环境的管理员/编辑员访问策略，否则 production 环境即使账号密码正确也无法进入 `/admin`。
2. 不得在生产环境直接执行完整 `DatabaseSeeder`。当前 Seeder 会创建 `test@example.com`、默认密码 `password` 的测试账号，存在严重安全风险。
3. 生产管理员必须使用独立账号、强密码和双因素认证；禁止使用测试账号、开发账号或示例密码。
4. 上述后台访问策略和生产管理员账号完成后，必须使用 `APP_ENV=production` 做一次登录验收，再进入正式部署。

## 1. 已确认的 VPS 环境

根据用户提供的宝塔面板截图，当前生产 VPS 已安装：

| 项目 | 已确认版本/状态 |
|---|---|
| 操作系统 | Ubuntu 24.04.4 LTS |
| Web 服务 | Nginx 1.28.3 |
| 数据库 | MySQL 8.0.45 |
| 缓存/队列服务 | Redis 8.0.5；`redis-cli ping` 已返回 `PONG`，服务端命令不在当前 PATH |
| PHP | PHP 8.3.33 |
| 前端构建 | Node.js v22.17.1，npm 10.9.2 |
| Node 进程工具 | PM2 5.6，当前不用于 Laravel 队列 |
| PHP 进程守护 | 宝塔“进程守护管理器” 3.0.6，基于 Supervisor |
| 数据库管理 | phpMyAdmin 5.2 |

当前仍需处理或确认：PHP CLI 的 OPcache/zip 重复加载警告、PHP-FPM、站点权限、宝塔进程守护管理器中的 Worker 配置和宝塔计划任务；Composer 2.10.3 已升级完成。

## 2. 本项目需要的基础环境

### 2.1 必须具备

- Ubuntu 24.04 LTS。
- Nginx 1.28.x。
- PHP 8.3-FPM 和 PHP 8.3 CLI。
- MySQL 8.0。
- Redis 8.x。
- Composer 2.x。
- 宝塔“进程守护管理器” 3.0.6；该工具基于 Supervisor，项目不另外安装系统 Supervisor。
- Cron 定时任务服务。
- Git、unzip、curl、ca-certificates、mysql-client、redis-cli 等基础工具。

### 2.2 PHP 扩展

在宝塔 PHP 8.3 扩展管理中确认以下扩展已经启用：

```text
bcmath
curl
dom
exif
fileinfo
gd
intl
mbstring
pdo_mysql
redis
pcntl
tokenizer
xml
zip
```

其中：

- `redis` 是 PHP 的 `phpredis` 扩展，不等于 Redis 服务本身。
- `pcntl` 主要供 CLI 队列 Worker 使用。
- `gd` 用于图片尺寸读取和相似图 dHash 计算。
- `exif` 用于图片 EXIF 信息读取。

不要因为项目使用腾讯云数据万象就额外安装 ImageMagick、FFmpeg 或 Python 图片服务；当前图片处理通过 PHP 代码、COS 和数据万象完成。

### 2.3 不需要安装

当前项目不需要安装：

- 百度或 Google 搜索接口。
- Meilisearch、Typesense 等外部搜索服务。
- Docker。
- PM2 作为 Laravel 队列守护工具。
- ImageMagick、FFmpeg。

项目当前使用 MySQL 站内搜索；PM2 仅在未来启用 Node.js SSR 时再考虑。

## 3. 上线前必须准备的资料

部署前准备以下信息，但不要把密钥发送到聊天、写入 Git 或提交到文档：

1. 正式域名，例如 `example.com`。
2. DNS 已解析到 VPS 公网 IP。
3. Laravel 项目目录：`/www/wwwroot/img.leomessi.cn`；宝塔网站对外运行目录必须设置为项目下的 `public`：`/www/wwwroot/img.leomessi.cn/public`。
4. 网站运行用户，推荐使用宝塔默认的 `www`。
5. 宝塔 PHP 8.3 的实际 CLI 路径，不能盲目假设为 `/usr/bin/php`。
6. MySQL 生产数据库名、独立数据库用户和密码。
7. Redis 连接方式、密码（如已启用）和端口。
8. 腾讯云 COS SecretId、SecretKey、地域、存储桶名称。
9. 数据万象已开通，并已绑定当前 COS 存储桶。
10. COS/CDN 展示域名和防盗链策略。
11. 数据库、`.env`、COS 对象和日志的备份位置。

腾讯云凭证应在项目后台“系统设置 → 存储与处理”中配置，并通过“检测 COS / 数据万象”或“上线前检查”验证；不要把 SecretId、SecretKey 写入本手册或 `.env`。

## 4. VPS 基础检查

通过 SSH 登录 VPS 后，先执行只读检查：

```bash
uname -a
cat /etc/os-release
nginx -v
mysql --version
redis-cli ping
php -v
php --ini
php -m | sort
composer --version
node -v
npm -v
crontab -l
```

预期结果：

- PHP CLI 与宝塔 PHP-FPM 都是 8.3.x。
- `php -m` 中包含本手册列出的 PHP 扩展。
- `redis-cli ping` 返回 `PONG`。
- Composer 为 2.x。
- Node.js 使用 LTS 版本。
- 宝塔“进程守护管理器”可以正常打开并显示运行状态。
- Cron 服务正常运行。

### 4.1 当前检测到的 PHP CLI / Composer 问题

当前检测结果显示 `putenv()` 已恢复，Composer 已升级到 2.10.3，但 OPcache 和 zip 仍存在重复加载警告。先清理 PHP CLI 重复配置，再继续生产部署。

在宝塔 PHP 8.3 的配置中处理：

1. 确认 CLI 配置中的 `disable_functions` 已不再禁用 `putenv`，不要为了修复 Composer 而额外放开其他函数。
2. 先备份 CLI 配置，再定位重复行：

```bash
cp -a /www/server/php/83/etc/php-cli.ini /www/server/php/83/etc/php-cli.ini.bak.$(date +%Y%m%d%H%M%S)
grep -nEi "opcache|zip" /www/server/php/83/etc/php-cli.ini
```

在 `php-cli.ini` 中只找到一条 `extension=zip`，并不能直接证明没有重复加载。重复来源可能是 PHP 内置模块或其他配置文件。先执行：

```bash
php -n -m | grep -iE "zip|opcache"
grep -RniE "^[[:space:]]*(zend_extension|extension)[[:space:]]*=.*(opcache|zip)" /www/server/php/83/etc /etc/php 2>/dev/null
```

处理分支：

- 如果 `php -n -m` 仍显示 `zip`，说明 zip 已由 PHP 内置加载；将 `php-cli.ini` 中的 `extension=zip` 行注释掉。
- 如果 `php -n -m` 不显示 `zip`，则保留 `php-cli.ini` 中这一条 `extension=zip`，并到第二条搜索结果对应的配置文件中注释重复项。
- OPcache 同样处理：只保留一条有效的 `zend_extension=...opcache...`。截图中的 `;zend_extension=opcache` 是注释行，不是当前有效加载项。
- 不要同时修改多个文件中的有效项；每次只注释一个确定的重复来源，然后重新验证。
3. 打开宝塔 PHP 8.3 的 CLI 配置文件：

```bash
nano /www/server/php/83/etc/php-cli.ini
```

在文件中分别搜索 `opcache` 和 `zip`：

- 如果看到两条有效的 `zend_extension` / `opcache` 加载项，只保留一条，将另一条行首加 `;` 注释掉。
- 如果看到两条有效的 `extension=zip` 加载项，只保留一条，将另一条行首加 `;` 注释掉。
- 不要把 OPcache 或 zip 的所有加载行都注释掉。
- 不要删除 `disable_functions` 中其他安全限制。
- 这一步只修复 PHP CLI；如果 PHP-FPM 日志也出现重复加载，再在宝塔 PHP-FPM 配置中单独处理，不要用 CLI 配置覆盖 FPM 配置。

4. 保存退出后重新验证：

```bash
php -r "var_dump(function_exists('putenv'), ini_get('disable_functions'));"
composer self-update --2
php -m | sort
composer --version
composer diagnose
```

预期结果是 `putenv` 可用、没有 OPcache/zip 重复加载警告，Composer 保持 2.10.3 或更高的稳定 Composer 2.x 版本，且不再出现 Symfony 旧组件的弃用警告。

5. 如通过宝塔图形化配置文件编辑器修改了 PHP-FPM 配置，再重启 PHP 8.3-FPM；只修改 CLI 配置时不需要重启 PHP-FPM。

如果 `php -v` 指向系统 PHP 而不是宝塔 PHP 8.3，后续所有 Artisan 命令，以及宝塔进程守护管理器中 Worker 的 PHP 命令，都必须使用宝塔 PHP 的绝对路径，例如：

```bash
/www/server/php/83/bin/php -v
```

实际路径必须以 VPS 上的检查结果为准。

## 5. 宝塔站点和系统安全配置

### 5.1 创建站点

在宝塔中创建站点并绑定正式域名：

- 网站目录：`/www/wwwroot/messiimage`
- 运行目录：`/www/wwwroot/messiimage/public`
- PHP 版本：PHP 8.3
- 运行用户：`www`
- 伪静态：使用 Laravel/Nginx 重写规则
- HTTPS：申请并启用正式证书

Laravel 的网站根目录必须是 `public/`，不能直接指向项目根目录，否则可能暴露 `.env`、`composer.json` 和应用源码。

### 5.2 防火墙和面板安全

公网只开放必要端口：

- 80：HTTP，用于跳转 HTTPS。
- 443：HTTPS。
- SSH：改为实际 SSH 端口，并限制来源 IP（条件允许时）。
- 宝塔面板端口：限制来源 IP，不对全网开放。

不要把 3306、6379 对公网开放。Redis 应只监听本机或内网地址，并启用密码或访问控制。

### 5.3 PHP 上传限制

项目支持后台批量上传图片。PHP 和 Nginx 的限制必须同时满足：

- `upload_max_filesize` 不小于单张图片最大允许大小。
- `post_max_size` 大于单批次所有图片大小总和。
- `max_file_uploads` 大于单批次最大图片数量。
- Nginx `client_max_body_size` 不小于 `post_max_size`。
- `memory_limit`、`max_execution_time` 按实际图片大小和批量规模调整。

示例起始值仅供上线前测试，不是固定要求：

```ini
upload_max_filesize = 50M
post_max_size = 300M
max_file_uploads = 20
memory_limit = 512M
max_execution_time = 120
```

修改后必须重启对应 PHP-FPM，并使用真实测试批次验证。

## 6. 创建 MySQL 数据库

在宝塔数据库管理中创建独立数据库和独立用户：

- 字符集：`utf8mb4`。
- 排序规则：使用 MySQL 8 默认的 `utf8mb4` 排序规则即可。
- 用户权限：只授予本项目数据库，不使用 root 连接应用。
- 远程访问：关闭，除非确实需要内网管理。

示例配置：

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=[生产数据库名]
DB_USERNAME=[项目数据库用户]
DB_PASSWORD=[项目数据库密码]
```

生产数据库迁移属于高风险操作。正式执行 `migrate --force` 前，必须确认数据库为空库或已经完成备份，并确认是否需要保留本地环境中的业务数据。

## 7. GitHub 首次上传与 VPS 获取项目代码

本项目当前 GitHub 远程仓库为：

```text
git@github.com:xicijiese/imgleomessi.git
```

项目本地首次上传时，在 Windows PowerShell 的项目目录执行。先检查状态，不要跳过敏感文件检查：

```powershell
cd F:\cursor\messiimage
git status --short
git branch --show-current
git remote -v
git ls-files .env
```

`.env`、真实腾讯云密钥、数据库密码、生产图片、`vendor`、`node_modules` 和日志不得提交。确认 `.env` 没有被跟踪后，再配置远程地址：

```powershell
git remote add origin git@github.com:xicijiese/imgleomessi.git
```

如果 `origin` 已存在但地址不正确，使用：

```powershell
git remote set-url origin git@github.com:xicijiese/imgleomessi.git
```

首次提交前必须先审查暂存内容：

```powershell
git add -A
git status
git diff --cached --stat
git diff --cached -- .env
```

确认无敏感信息后再提交并推送：

```powershell
git commit -m "chore: prepare production deployment"
git branch -M main
git push -u origin main
```

GitHub 私有仓库推荐使用 SSH Deploy Key；不要把 Token 写进 Git URL、命令历史或项目文件。

SSH 公钥和私钥口令不是同一个东西，必须严格区分：

- `id_rsa.pub` 是公钥，只能复制到 GitHub 的“Settings → SSH and GPG keys → New SSH key”页面；
- `id_rsa` 是本机私钥，不能复制到 GitHub，也不能发给任何人；
- `Enter passphrase for key ...` 要求输入的是创建 `id_rsa` 时设置的私钥口令，不是公钥文本、GitHub 密码或 VPS 密码；
- 如果创建密钥时没有设置口令，在该提示处直接按回车；输入口令时终端不会显示字符；
- 如果忘记私钥口令，无法从 `id_rsa` 恢复，只能生成新密钥并把新的 `.pub` 公钥添加到 GitHub。

正确流程如下：

```powershell
# 只把下面的公钥文本复制到 GitHub 网页，不要复制到 SSH 口令提示处
Get-Content $env:USERPROFILE\.ssh\id_rsa.pub | Set-Clipboard

# 测试时只输入创建 id_rsa 时设置的私钥口令；如果没有口令，直接按回车
ssh -o IdentitiesOnly=yes -i $env:USERPROFILE\.ssh\id_rsa -T git@github.com
```

测试成功后，才执行 `git push`。首次上传完成后，确认 GitHub 页面能够看到 `artisan`、`composer.json`、`package.json` 和 `public` 目录，再进行 VPS 部署。

### 7.1 VPS 克隆到宝塔网站项目目录

项目目录与网站对外目录必须区分：

```text
Laravel 项目目录：/www/wwwroot/img.leomessi.cn
宝塔网站运行目录：/www/wwwroot/img.leomessi.cn/public
```
如果 GitHub 仓库为私有仓库，VPS 需要单独生成自己的只读 Deploy Key，不能复制本机的私钥到 VPS：

```bash
ssh-keygen -t ed25519 -C "imgleomessi VPS read-only deploy" \
  -f /root/.ssh/imgleomessi_vps_deploy
cat /root/.ssh/imgleomessi_vps_deploy.pub
```

将输出的公钥添加到 GitHub 仓库 `Settings → Deploy keys`，不要勾选 `Allow write access`。然后在 VPS 上验证：

```bash
ssh -o IdentitiesOnly=yes \
  -i /root/.ssh/imgleomessi_vps_deploy -T git@github.com
```

SSH 登录 VPS 后，先检查目录。目录为空时执行：

```bash
sudo -i
git clone --branch main --single-branch \
  git@github.com:xicijiese/imgleomessi.git \
  /www/wwwroot/img.leomessi.cn
```

如果目录中只有宝塔默认页面或其他未知文件，不要直接删除；先保留备份，再克隆：

```bash
stamp=$(date +%Y%m%d%H%M%S)
mv /www/wwwroot/img.leomessi.cn \
  /www/wwwroot/img.leomessi.cn.bt-backup.$stamp

git clone --branch main --single-branch \
  git@github.com:xicijiese/imgleomessi.git \
  /www/wwwroot/img.leomessi.cn
```

如果目录已经是本项目的 Git 工作区，则不要重复克隆：

```bash
cd /www/wwwroot/img.leomessi.cn
git remote -v
git fetch origin
git checkout main
git pull --ff-only origin main
```

生产环境不要上传以下内容：

- `.env` 的开发版本；
- `node_modules`；
- 本地数据库文件；
- 测试图片和测试备份；
- 调试日志和临时文件。

设置目录权限：

```bash
chown -R www:www /www/wwwroot/img.leomessi.cn
chmod -R ug+rwX /www/wwwroot/img.leomessi.cn/storage \
  /www/wwwroot/img.leomessi.cn/bootstrap/cache
```

不要使用 `chmod -R 777`。克隆完成后，在宝塔网站设置中将网站目录改为 `/www/wwwroot/img.leomessi.cn/public`，不能直接指向项目根目录。
## 8. 安装 Laravel 依赖

确认当前目录包含 `composer.json` 和 `composer.lock` 后执行：

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

如果 Composer 不在 PATH 中，应使用宝塔 Composer 的实际绝对路径。

不要在生产环境执行 `composer update`，生产部署应严格依据 `composer.lock` 安装依赖。

## 9. 创建生产 `.env`

复制 `.env.example` 后，只在 VPS 上编辑：

```bash
cp .env.example .env
```

生产环境至少确认以下配置：

```dotenv
APP_NAME="梅西影像档案库"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://[正式域名]

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=[生产数据库名]
DB_USERNAME=[项目数据库用户]
DB_PASSWORD=[项目数据库密码]

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=[Redis密码或null]
REDIS_PORT=6379

DB_QUEUE_RETRY_AFTER=150
REDIS_QUEUE_RETRY_AFTER=150

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning
```

说明：

- 如果 `.env` 中没有有效的 `APP_KEY`，只在第一次初始化时执行 `php artisan key:generate`。
- 已经存在生产数据后，绝不能重新生成 `APP_KEY`，否则历史加密数据和会话可能无法解密。
- 腾讯云 COS / 数据万象凭证由后台系统设置加密保存，不写入教程、不提交 Git。
- `FILESYSTEM_DISK` 是 Laravel 默认文件系统配置；项目实际图片存储方式还需要在后台选择“本地”或“腾讯云 COS”。生产推荐后台选择 COS。

## 10. 初始化数据库和 Laravel 缓存

确认数据库备份、`.env` 和生产管理员准备完成后，再执行：

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

不要直接执行：

```bash
php artisan db:seed --force
```

当前完整 Seeder 会创建开发测试账号。若生产确实需要初始化固定分类、赞助方案或勋章，应逐个审核对应 Seeder 后单独执行，禁止无审查地导入演示数据。

## 11. 构建前端资源

项目已经安装 Node.js 版本管理器。推荐在 VPS 或可信构建机使用 Node.js LTS：

```bash
node -v
npm -v
npm ci
npm run build
```

如果选择在本地或 CI 构建，也可以只把生成后的 `public/build` 和锁定的后端依赖一起发布到 VPS；但每次前端代码变更后必须重新构建。

当前项目不要求启用 Inertia SSR。只有在明确启用 SSR 后才执行：

```bash
npm run build:ssr
```

## 12. 配置 Supervisor 队列 Worker

项目的图片派生图、OCR、智能标签、哈希和相似候选等耗时任务必须异步执行，不能依赖用户请求同步完成。

在宝塔“进程守护管理器”中新增一个 Laravel 队列进程。以下是配置模板，实际 PHP 路径、项目目录和运行用户必须按 VPS 检查结果修改：

```ini
[program:messiimage-worker]
command=/www/server/php/83/bin/php /www/wwwroot/messiimage/artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3 --timeout=120 --max-time=3600
directory=/www/wwwroot/messiimage
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwlogs/messiimage-worker.log
stopwaitsecs=360
```

队列参数约束：

- Worker `--timeout=120`。
- Redis `retry_after=150`。
- `timeout` 必须小于 `retry_after`，避免任务被重复领取。
- 初次上线使用 `numprocs=1`，观察 CPU、内存和失败任务后再扩容。

保存后只在宝塔“进程守护管理器”中启动，并确认状态为运行中。进程的保存、启动、重启和日志查看全部通过宝塔工具完成。

保存或修改配置后，在宝塔“进程守护管理器”中执行保存、启动、重启和查看日志。

发布队列代码后执行：

```bash
php artisan queue:restart
```

然后在宝塔“进程守护管理器”中重启 `messiimage-worker`。

失败任务排查：

```bash
php artisan queue:failed
php artisan queue:retry all
```

## 13. 配置 Laravel 定时任务

当前项目没有把耗时图片处理放进 Web 请求，但仍应配置 Laravel 标准调度入口，便于后续定时备份、清理和巡检任务运行。

在宝塔“计划任务”中新增一条每分钟执行的 Shell 任务，不要直接编辑宝塔生成的系统 crontab：

```cron
* * * * * cd /www/wwwroot/messiimage && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1
```

PHP 路径必须替换为 VPS 上实际的 PHP 8.3 CLI 路径。

## 14. 配置腾讯云 COS / 数据万象

部署完成并能登录后台后：

1. 进入“系统设置 → 存储与处理”。
2. 存储方式选择“腾讯云 COS”。
3. 填写 SecretId、SecretKey、地域和存储桶。
4. 开启数据万象处理开关。
5. 如已配置 CDN，填写展示图/CDN 域名。
6. 保存配置。
7. 使用“检测 COS / 数据万象”或“上线前检查”。
8. 选择已经存在于当前 COS 存储桶中的原图进行验证。

检测范围：

- COS 临时写入、读取、删除。
- 代表性原图是否存在。
- 展示图和缩略图对象是否存在。
- 数据万象标签接口是否可用。
- CDN 地址格式是否正确。

本检查不会自动迁移本地历史图片，也不会删除 COS 文件或切换生产流量。

## 15. 上线前检查清单

### 应用和后台

- [ ] `APP_ENV=production`。
- [ ] `APP_DEBUG=false`。
- [ ] `APP_KEY` 已配置且不再重复生成。
- [ ] 生产管理员不是测试账号。
- [ ] 生产环境可登录 `/admin`。
- [ ] 管理员具备双因素认证。
- [ ] 后台生产访问策略已通过验证。

### Web 和 PHP

- [ ] Nginx 根目录是 `public/`。
- [ ] HTTPS 正常，HTTP 自动跳转 HTTPS。
- [ ] PHP-FPM 使用 PHP 8.3。
- [ ] PHP CLI 与 Supervisor 使用同一套 PHP 8.3。
- [ ] PHP 扩展完整。
- [ ] 上传限制满足实际批量上传需求。
- [ ] `storage` 和 `bootstrap/cache` 可由 `www` 写入。

### 数据库、缓存和队列

- [ ] MySQL 使用独立数据库用户。
- [ ] Redis 返回 `PONG`。
- [ ] `CACHE_STORE=redis`。
- [ ] `SESSION_DRIVER=redis`。
- [ ] `QUEUE_CONNECTION=redis`。
- [ ] Supervisor Worker 状态为运行中。
- [ ] Worker 日志没有持续报错。
- [ ] `php artisan queue:failed` 无异常积压。

### 图片链路

- [ ] 后台存储模式已选择 COS。
- [ ] COS / 数据万象检测通过。
- [ ] 上传一张测试图片后，原图写入 COS。
- [ ] 展示图和缩略图生成成功。
- [ ] SHA-256、OCR、智能标签和相似候选任务状态符合预期。
- [ ] 发布图片后首页、图库列表和详情页可见。
- [ ] 未发布图片不会出现在前台。
- [ ] 当前没有误迁移或误删除本地历史图片。

### 安全和运维

- [ ] 3306、6379 未对公网开放。
- [ ] 宝塔面板和 SSH 已限制来源。
- [ ] MySQL 已配置每日备份。
- [ ] `.env` 已加密备份但未进入 Git。
- [ ] COS 文件误删恢复方案已经确认。
- [ ] Laravel 日志和 Worker 日志有保留策略。
- [ ] 磁盘使用率、队列失败和 PHP/Nginx 错误有监控。

## 16. 正式发布顺序

正式切流前建议按以下顺序执行：

1. 进入维护窗口并记录当前代码版本。
2. 备份 MySQL、`.env` 和当前发布目录。
3. 上传或拉取新代码。
4. 执行 `composer install --no-dev --prefer-dist --optimize-autoloader`。
5. 执行 `npm ci && npm run build`，或发布已验证的 `public/build`。
6. 检查并更新 `.env`，不覆盖现有 `APP_KEY`。
7. 执行 `php artisan migrate --force`，仅在迁移已审核并完成备份后执行。
8. 执行 `php artisan optimize:clear`、`config:cache`、`route:cache`、`view:cache`。
9. 重启 PHP-FPM。
10. 重启或平滑重启 Supervisor Worker。
11. 执行后台上线前检查和 COS / 数据万象检测。
12. 手动检查首页、页脚、图库、搜索、登录、后台、图片上传和图片处理。
13. 确认无误后退出维护模式并开放正式流量。

## 17. 回滚方案

如果新版本出现严重错误：

1. 立即停止新增上传和发布操作。
2. 保留错误日志、Worker 日志和当前版本信息。
3. 进入维护模式。
4. 停止当前 Worker，恢复上一份代码和 `public/build`。
5. 恢复上一份 `.env`，不得重新生成 `APP_KEY`。
6. 重建 Composer 和 Laravel 缓存。
7. 重启 PHP-FPM 和 Supervisor Worker。
8. 先检查首页、登录、后台和已有图片，再退出维护模式。
9. 只有在确认数据库迁移导致不可逆问题时，才根据备份执行数据库恢复；数据库恢复前必须再次确认影响范围。

## 18. 常见故障排查

### 首页 500

```bash
php artisan optimize:clear
tail -n 200 storage/logs/laravel.log
```

检查 PHP 版本、扩展、`.env`、数据库连接和文件权限。

### 后台登录失败或无权限

检查生产后台访问策略、管理员账号状态、`APP_ENV` 和 Filament 面板授权逻辑。不要使用 `test@example.com` 或开发环境账号。

### 队列不处理

```bash
redis-cli ping
php artisan queue:failed
在宝塔“进程守护管理器”中查看 `messiimage-worker` 状态和日志。
tail -n 200 /www/wwwlogs/messiimage-worker.log
```

重点检查 PHP CLI 路径、Redis PHP 扩展、运行用户、项目目录权限和 `QUEUE_CONNECTION`。

### 图片上传成功但首页不显示

检查图片是否已发布、是否完成必选分类、展示图/缩略图是否生成、COS 对象是否存在、CDN 域名是否正确，以及前台是否读取了当前配置的存储地址。

### COS / 数据万象失败

检查 SecretId、SecretKey、地域、存储桶、数据万象绑定关系和 COS 原图 Key。后台检测必须选择当前 COS 存储桶中已经存在的原图。

## 19. 当前未执行事项

截至本手册整理时，以下操作仍未执行：

- 未连接真实生产 VPS。
- 未创建或迁移生产数据库。
- 本地项目已配置 GitHub `origin`，但尚未完成首次提交和推送。
- 尚未在 VPS 克隆生产代码。
- 未配置真实生产 `.env`。
- 未配置 Supervisor Laravel Worker。
- 未执行生产数据库迁移。
- 未配置正式域名和 HTTPS。
- 未执行历史图片迁移。
- 未配置 CDN 防盗链。
- 未进行正式生产流量切换。

这些操作必须在生产后台访问策略、管理员账号、备份和回滚方案确认后，按本手册逐项执行。
