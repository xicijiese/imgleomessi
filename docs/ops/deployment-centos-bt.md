# 运维与部署文档：Ubuntu 24.04 + 宝塔（历史文件名保留）

本文档是本项目进入真实生产环境前的准备、部署、验收、备份和回滚手册。

本项目采用 Laravel 单体应用：Nginx 提供 Web 服务，PHP-FPM 运行 Laravel，MySQL 保存业务数据，Redis 提供缓存/会话/队列，宝塔“进程守护管理器”（基于 Supervisor）守护 Laravel 队列 Worker，腾讯云 COS / 数据万象负责生产图片存储和图片处理。

本文档描述生产部署方案；本次已完成 GitHub 首次推送和 VPS 代码拉取演练，但不代表已经完成生产切流。所有带有“确认后执行”的命令，都必须在备份、维护窗口和回滚方案准备完成后执行。
## 本次已完成规则（2026-09-09）

- local 存储使用 PHP GD 生成展示图和缩略图；COS 存储使用腾讯云数据万象生成相同规格的 WebP 派生图。
- 展示图最大 2048×2048，缩略图最大 600×600；两种模式统一写入 `display_key` 和 `thumbnail_key`。
- 每张新上传图片都会创建派生图处理任务，必须由宝塔进程守护管理器中的 Laravel Worker 消费；计划任务 `schedule:run` 不能代替 Worker。
- 图片缺少展示图或缩略图时不能发布；自动发布还要求 metadata、hash 和最新派生图任务完成。历史失败任务重试成功后按最新任务状态判断。
- 只在后台修改数据库中的存储方式不要求重启 Worker；修改 PHP 代码、`.env`、配置缓存或 Worker 命令后必须执行 `queue:restart` 并重启宝塔 Worker。
- local 生产链路必须确认 PHP CLI 已启用 GD 和 WebP；COS 生产链路必须确认 COS、数据万象和 CDN 配置均通过后台检测。

## 0. 当前生产部署阻塞项

在执行真实生产部署前，必须先完成以下代码和账号安全确认：

1. 已补齐生产环境管理员访问策略：数据库新增 users.role，admin / editor 可以进入 /admin；首次生产部署必须执行 app:create-admin 创建独立管理员，不能依赖测试 Seeder。
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

已完成：PHP CLI 的 OPcache/zip 重复加载警告清理、putenv 恢复、Composer 2.10.3 升级、GitHub 代码仓库准备和宝塔 Laravel Worker 启动验证；仍需在正式上线前确认 PHP-FPM、站点权限、计划任务、生产数据库和域名 HTTPS。

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

本节记录本次 VPS 实测排障过程，供下次部署复用。当前结果已经收口：`putenv()` 已恢复，Composer 已升级到 2.10.3，OPcache 和 zip 的重复加载已清理；`php -m` 只显示一个 `Zend OPcache` 和一个 `zip`，`composer diagnose` 已通过。若新环境再次出现警告，按下面的分支定位，不要凭截图只修改 `php-cli.ini` 中看到的那一行。

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

- 网站目录：`/www/wwwroot/img.leomessi.cn`
- 运行目录：`/www/wwwroot/img.leomessi.cn/public`
- PHP 版本：PHP 8.3
- 运行用户：`www`
- 伪静态：使用 Laravel/Nginx 重写规则
### 5.1.1 Laravel Nginx 伪静态规则

宝塔站点的运行目录必须是：

~~~text
/www/wwwroot/img.leomessi.cn/public
~~~

然后在宝塔网站的“伪静态”配置中填写以下 Laravel Nginx 规则。这里只填写 location 规则，不要额外包裹 server、http 或 events 配置：

~~~nginx
# Filament/Livewire 的动态资源路径必须先交给 Laravel
location ^~ /livewire- {
    try_files $uri $uri/ /index.php?$query_string;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
~~~

这两条规则的作用是：真实存在的静态文件继续由 Nginx 直接返回，Laravel 前台路由和 Filament/Livewire 动态请求统一回退到 public/index.php。Filament/Livewire 当前版本会生成带哈希的 `/livewire-xxxx/` 路径；缺少专用的 `location ^~ /livewire-` 时，Nginx 的静态资源规则可能直接返回 404，导致后台登录表单无法提交或后台主体白屏。

缺少通用回退规则时，首页可能可以打开，但 /photos、/albums、/topics、/search 等 Laravel 路由会被 Nginx 直接返回 404。

保存伪静态配置后，在宝塔面板重载 Nginx。不要把这段规则写入项目根目录的 .htaccess；当前生产环境使用 Nginx，不依赖 Apache 的 .htaccess。
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

推荐为本仓库单独使用一把 Windows SSH 密钥，避免和其他 GitHub 仓库混用。若该文件已经存在，不要重复生成：

~~~powershell
ssh-keygen -t ed25519 -C "imgleomessi GitHub push" -f $env:USERPROFILE\.ssh\github_imgleomessi_deploy
~~~

如果生成时看到：

~~~text
Enter passphrase (empty for no passphrase):
~~~

想使用无口令部署时直接按回车；随后 `Enter same passphrase again:` 再按一次回车。若设置了口令，之后 `Enter passphrase for key ...` 要输入的是这把私钥创建时设置的口令，终端不会显示字符。公钥文本绝对不能粘贴到这个口令提示处。

只复制公钥到 GitHub：

~~~powershell
Get-Content $env:USERPROFILE\.ssh\github_imgleomessi_deploy.pub | Set-Clipboard
~~~

将剪贴板内容粘贴到仓库 `Settings → Deploy keys → Add deploy key`。本机需要推送代码时勾选 `Allow write access`；私钥文件 `github_imgleomessi_deploy` 只保留在本机，不能上传、发送或复制到 VPS。

然后用同一把私钥验证：

~~~powershell
ssh -o IdentitiesOnly=yes -i $env:USERPROFILE\.ssh\github_imgleomessi_deploy -T git@github.com
~~~

如果当前电脑已有其他密钥，也必须用 `-i` 指定本仓库密钥。首次连接出现主机指纹确认时输入小写 `yes`；这不是私钥口令。认证成功会显示 GitHub 已认证但不提供 Shell。

将该密钥绑定到本地仓库，确保后续 `git push` 使用的是刚才验证过的密钥：

~~~powershell
git config --local core.sshCommand "ssh -o IdentitiesOnly=yes -i C:/Users/xicij/.ssh/github_imgleomessi_deploy"
~~~


测试成功后，才执行 `git push`。首次上传完成后，确认 GitHub 页面能够看到 `artisan`、`composer.json`、`package.json` 和 `public` 目录，再进行 VPS 部署。

### 7.1 VPS 获取代码并接入宝塔网站项目目录

项目目录与网站对外目录必须区分：

```text
Laravel 项目目录：/www/wwwroot/img.leomessi.cn
宝塔网站运行目录：/www/wwwroot/img.leomessi.cn/public
```
如果 GitHub 仓库为私有仓库，必须在 VPS 上单独生成一把只读 Deploy Key。不要把 Windows 本机的私钥复制到 VPS，也不要把 VPS 私钥复制到 GitHub 网页。下面每一步的输入和复制位置必须按说明执行。

**第 1 步：在 VPS 生成密钥**

```bash
sudo -i
mkdir -p /root/.ssh
chmod 700 /root/.ssh
ssh-keygen -t ed25519 \
  -C "imgleomessi VPS read-only deploy" \
  -f /root/.ssh/imgleomessi_vps_deploy
```

执行后会出现两个提示：

```text
Enter passphrase (empty for no passphrase):
```

这里**不要输入任何内容，直接按回车**。

接着会出现：

```text
Enter same passphrase again:
```

这里也**不要输入任何内容，直接再按一次回车**。

这一步不会让你复制公钥，也不会让你输入 GitHub 密码。按回车后会生成两个文件：

```text
/root/.ssh/imgleomessi_vps_deploy       # 私钥，只留在 VPS，绝对不要复制或发送
/root/.ssh/imgleomessi_vps_deploy.pub   # 公钥，稍后复制到 GitHub
```

**第 2 步：只查看公钥**

```bash
cat /root/.ssh/imgleomessi_vps_deploy.pub
```

终端会输出一整行，以 `ssh-ed25519` 开头，以 `imgleomessi VPS read-only deploy` 结尾。只复制这一整行公钥，复制到 GitHub 网页；不要复制 `/root/.ssh/imgleomessi_vps_deploy` 私钥文件的内容。

**第 3 步：把公钥添加到 GitHub**

打开仓库的 `Settings → Deploy keys → Add deploy key`，填写：

```text
Title: imgleomessi VPS read-only deploy
Key: 粘贴刚才 cat 命令输出的整行 ssh-ed25519 公钥
```

不要勾选 `Allow write access`，然后点击 `Add key`。这里不需要把任何内容再粘回 VPS 命令行。

**第 4 步：在 VPS 验证 GitHub 认证**

```bash
chmod 600 /root/.ssh/imgleomessi_vps_deploy
chmod 644 /root/.ssh/imgleomessi_vps_deploy.pub
ssh -o IdentitiesOnly=yes \
  -i /root/.ssh/imgleomessi_vps_deploy -T git@github.com
```

第一次连接如果出现：

```text
Are you sure you want to continue connecting (yes/no/[fingerprint])?
```

这里输入小写 `yes` 并回车。这是确认 GitHub 主机指纹，不是私钥口令。

认证成功会看到类似：

```text
Hi xicijiese! You've successfully authenticated, but GitHub does not provide shell access.
```

**第 5 步：将项目接入宝塔网站根目录**

先进入已经由宝塔创建的网站根目录：

~~~bash
cd /www/wwwroot/img.leomessi.cn
~~~

先查看根目录现有内容：

~~~bash
find /www/wwwroot/img.leomessi.cn -mindepth 1 -maxdepth 1 -printf '%f\n'
~~~

如果现有内容只有宝塔 SSL / Let's Encrypt 使用的 `.well-known` 目录，可以直接在这个目录初始化 Git。`.well-known` 必须保留，不能删除、移动或整体替换网站根目录。

不要对包含 `.well-known` 的目录执行 `git clone`：Git 会因为目标目录非空而报 `destination path ... already exists and is not an empty directory`。本次实测采用下面的直接接入方式：

~~~bash
cd /www/wwwroot/img.leomessi.cn

git init

# 宝塔创建的目录可能归 www 所有，而当前命令由 root 执行；
# 遇到 dubious ownership 时，先把项目目录加入当前用户的安全目录列表。
git config --global --add safe.directory /www/wwwroot/img.leomessi.cn

export GIT_SSH_COMMAND='ssh -o IdentitiesOnly=yes -i /root/.ssh/imgleomessi_vps_deploy'

git remote add origin git@github.com:xicijiese/imgleomessi.git
git fetch origin main
git checkout -b main --track origin/main
~~~

`git remote add origin` 如果提示 `remote origin already exists`，不要重复添加；先检查并继续：

~~~bash
git remote -v
git fetch origin main
git checkout main
git pull --ff-only origin main
~~~

这里不会删除 `.well-known`，也不会把它上传到 GitHub。拉取完成后验证：

~~~bash
test -d /www/wwwroot/img.leomessi.cn/.well-known \
  && echo ".well-known 保留正常"

test -f /www/wwwroot/img.leomessi.cn/artisan \
  && echo "Laravel 项目已拉取"

git status --short
~~~

首次接入时如果只看到：

~~~text
?? .well-known/
~~~

这是正常结果：该目录是 VPS 上的 SSL 文件，不属于 GitHub 项目。为了避免每次 `git status` 都显示它，可以只在当前 VPS 工作区的本地排除文件中记录：

~~~bash
printf '/.well-known/\n' >> .git/info/exclude
git status --short
~~~

`.git/info/exclude` 不会修改项目源码，也不会提交到 GitHub；不要把 `.well-known` 加入全局 `.gitignore`，以免影响其他环境。

如果目录中除 `.well-known` 外还有未知业务文件，不要继续覆盖；先逐项核对并把确认无用的默认文件单独移到带时间戳的备份目录。不要整体移动、删除或重建包含 `.well-known` 的网站根目录。

后续发布新版本时，在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
export GIT_SSH_COMMAND='ssh -o IdentitiesOnly=yes -i /root/.ssh/imgleomessi_vps_deploy'
git fetch origin main
git checkout main
git pull --ff-only origin main
~~~

以上命令只允许从 GitHub 拉取代码，不允许在 VPS 上直接 `git push`。VPS 的 Deploy Key 不勾选 `Allow write access`。
生产环境不要上传以下内容：

- `.env` 的开发版本；
- `node_modules`；
- 本地数据库文件；
- 测试图片和测试备份；
- 调试日志和临时文件。

设置目录权限（命令在项目根目录执行）：

```bash
chown -R www:www /www/wwwroot/img.leomessi.cn
chmod -R ug+rwX /www/wwwroot/img.leomessi.cn/storage \
  /www/wwwroot/img.leomessi.cn/bootstrap/cache
```

不要使用 `chmod -R 777`。代码获取完成后，在宝塔网站设置中将网站目录改为 `/www/wwwroot/img.leomessi.cn/public`，不能直接指向项目根目录。
## 8. 安装 Laravel 依赖

以下命令全部在 VPS 的 Laravel 项目根目录执行。每次重新 SSH 登录后，都要先执行第一行 cd：

~~~bash
cd /www/wwwroot/img.leomessi.cn
pwd
test -f composer.json && echo "composer.json 存在"
test -f composer.lock && echo "composer.lock 存在"
/www/server/php/83/bin/php -v
command -v composer
composer --version
~~~

确认 PHP 版本为宝塔 PHP 8.3、Composer 可以正常运行后，仍在同一个目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
composer install --no-dev --prefer-dist --optimize-autoloader
~~~

安装完成后验证：

~~~bash
cd /www/wwwroot/img.leomessi.cn
test -f vendor/autoload.php && echo "Composer 依赖安装完成"
~~~

Filament 后台使用独立的 CSS、JavaScript 和 Livewire 组件注册缓存。Composer 安装完成后，必须仍在项目根目录执行一次资产发布和组件缓存；这不是 Inertia 前端的 npm run build，不能用前端构建替代：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan filament:upgrade
/www/server/php/83/bin/php artisan filament:cache-components
test -f public/css/filament/filament/app.css && echo "Filament CSS 已发布"
test -f public/js/filament/filament/app.js && echo "Filament JS 已发布"
test -f bootstrap/cache/filament/panels/admin.php && echo "Filament 组件缓存已生成"
~~~

filament:upgrade 会重新发布后台资产并清理 Filament 的配置、路由和视图缓存；filament:cache-components 会根据当前代码重新发现后台资源、页面和 Livewire 组件。每次拉取包含 Filament 后台代码或 Composer 依赖的更新后，都要重新执行这两条命令。

如果 composer 不在 PATH 中，应使用宝塔 Composer 的实际绝对路径。不要在生产环境执行 composer update，生产环境必须依据 composer.lock 安装依赖。

## 9. 创建生产 .env

### 9.1 先创建生产数据库

数据库创建在宝塔面板“数据库”中完成，不是在 SSH 项目目录中执行：

1. 创建独立 MySQL 数据库。
2. 创建独立数据库用户。
3. 字符集使用 utf8mb4。
4. 只授予该用户访问本项目数据库的权限。
5. 不使用 MySQL root 连接 Laravel。
6. 不开启 3306 公网访问。

数据库名称、用户名和密码准备好后，可以先在项目根目录测试数据库连接：

~~~bash
cd /www/wwwroot/img.leomessi.cn
mysql -h 127.0.0.1 -P 3306 -u [项目数据库用户] -p -D [生产数据库名] -e "SELECT 1;"
~~~

执行后输入数据库密码。命令返回 1，说明 MySQL 账号、密码和数据库连接正常；不要把密码直接写在命令参数中。确认连接正常后，再回到 VPS 项目根目录创建 .env。

### 9.2 创建并编辑生产 .env

以下命令全部在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
test -f .env || cp .env.example .env
chmod 640 .env
nano .env
~~~

在 nano 中填写生产值。保存按 Ctrl+O、回车；退出按 Ctrl+X。

至少确认以下内容：

~~~dotenv
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
SESSION_COOKIE=imgleomessi_session
SESSION_DOMAIN=null
SESSION_PATH=/
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false

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
~~~

编辑完成后，在项目根目录检查文件和权限。下面的 grep 不会输出数据库密码、Redis 密码或腾讯云密钥：

~~~bash
cd /www/wwwroot/img.leomessi.cn
test -f .env && echo ".env 已创建"
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|QUEUE_CONNECTION|CACHE_STORE|SESSION_DRIVER|REDIS_HOST|REDIS_PORT)=' .env
chown www:www .env
chmod 640 .env
~~~

### 9.3 生成 APP_KEY

仅当这是全新的生产 .env 且 APP_KEY 为空时，在项目根目录执行一次：

~~~bash
cd /www/wwwroot/img.leomessi.cn
grep '^APP_KEY=' .env
/www/server/php/83/bin/php artisan key:generate --force
~~~

如果已有生产数据或已有有效 APP_KEY，禁止再次执行 key:generate。重新生成会导致历史加密数据和会话无法解密。

腾讯云 COS / 数据万象凭证在部署完成后的生产后台“系统设置 → 存储与处理”中填写和加密保存，不写入本手册、不写入 .env、不提交 Git。

## 10. 初始化生产数据库和 Laravel 缓存

### 10.1 先验证 Laravel 实际读取到的数据库配置

在执行 migrate:status 之前，必须先确认 Laravel 读取的不是默认配置。以下命令全部在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
pwd
test -f .env && echo ".env 存在"
grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME)=' .env
if grep -q '^DB_PASSWORD=.' .env; then echo "DB_PASSWORD 已填写"; else echo "DB_PASSWORD 为空"; fi
test -f bootstrap/cache/config.php && echo "发现配置缓存：需要清理"
~~~

上面的检查不会打印数据库密码。然后清理可能残留的默认配置缓存：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan optimize:clear
~~~

再让 Laravel 输出实际生效的连接类型、主机、数据库名和用户名。此命令不会输出密码：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php -r 'require "/www/wwwroot/img.leomessi.cn/vendor/autoload.php"; $app=require "/www/wwwroot/img.leomessi.cn/bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach (["default" => config("database.default"), "host" => config("database.connections.mysql.host"), "database" => config("database.connections.mysql.database"), "username" => config("database.connections.mysql.username")] as $key => $value) echo $key."=".$value.PHP_EOL;'
~~~

预期结果应类似：

~~~text
"mysql"
"127.0.0.1"
"img_leomessi_cn"
"img_leomessi_cn"
~~~

如果仍然显示 root、laravel 或其他默认值，不要继续执行迁移。回到项目根目录编辑 .env：

~~~bash
cd /www/wwwroot/img.leomessi.cn
nano .env
~~~

确认以下五项使用真实生产值，等号两边不要多写空格，方括号不要保留：

~~~dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=img_leomessi_cn
DB_USERNAME=img_leomessi_cn
DB_PASSWORD=这里填写数据库真实密码
~~~

保存后再次清理缓存并验证：

~~~bash
cd /www/wwwroot/img.leomessi.cn
chown www:www .env
chmod 640 .env
/www/server/php/83/bin/php artisan optimize:clear
/www/server/php/83/bin/php -r 'require "/www/wwwroot/img.leomessi.cn/vendor/autoload.php"; $app=require "/www/wwwroot/img.leomessi.cn/bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach (["default" => config("database.default"), "database" => config("database.connections.mysql.database"), "username" => config("database.connections.mysql.username")] as $key => $value) echo $key."=".$value.PHP_EOL;'
~~~

如果 Laravel 仍然读取旧值，再检查当前 SSH 会话是否设置了会覆盖 .env 的 DB 环境变量：

~~~bash
cd /www/wwwroot/img.leomessi.cn
for key in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
  if printenv "$key" >/dev/null; then echo "$key 已存在于当前 SSH 环境"; fi
done
~~~

如果发现这些变量来自当前 SSH 会话，先清除它们，再清理 Laravel 配置缓存：

~~~bash
cd /www/wwwroot/img.leomessi.cn
unset DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
/www/server/php/83/bin/php artisan optimize:clear
~~~

只有 Laravel 实际读取到正确的数据库名和用户名后，才继续执行迁移：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan migrate:status
~~~

### 10.2 执行生产迁移和缓存构建

以下命令全部在项目根目录执行。执行迁移前必须确认生产数据库已创建、.env 连接信息正确，并且已有数据库已完成备份：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan migrate --force
~~~

迁移完成后再次查看状态：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan migrate:status
~~~

然后清理并重建 Laravel 缓存：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan optimize:clear
/www/server/php/83/bin/php artisan filament:upgrade
/www/server/php/83/bin/php artisan filament:cache-components
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan route:cache
/www/server/php/83/bin/php artisan view:cache
~~~

上面的 Filament 两条命令必须在 config:cache、route:cache 和 view:cache 之前执行。它们负责后台资产和组件发现；npm ci / npm run build 只负责 Inertia 前台资源，两者用途不同。

不要执行完整 db:seed --force。当前完整 Seeder 会创建开发测试账号。生产环境如需初始化固定分类、赞助方案或勋章，必须逐个审核对应 Seeder 后再单独执行。

如果迁移报错，不要反复执行 migrate --force；先保留错误信息，检查 .env、数据库权限、PHP 扩展和迁移状态。
### 10.3 创建生产管理员账号

生产环境没有默认管理员账号。不要执行完整 DatabaseSeeder，因为它会创建开发测试账号 test@example.com / password。

完成数据库迁移后，在 VPS 项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan app:create-admin
~~~

命令会依次询问：

1. 管理员姓名；
2. 管理员邮箱；
3. 管理员密码；
4. 确认管理员密码。

密码至少 12 个字符。输入密码时终端不会显示字符，这是正常的。密码只会写入数据库哈希，不会写入代码、日志或 Git。创建成功后，用刚才填写的邮箱和密码访问 /admin。

不要把本节输入的真实账号密码记录到部署文档、聊天记录或 Git 仓库。
## 11. 构建前端资源

前端构建也必须在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
pwd
node -v
npm -v
test -f package-lock.json && echo "package-lock.json 存在"
npm ci
npm run build
~~~

构建完成后验证产物：

~~~bash
cd /www/wwwroot/img.leomessi.cn
test -f public/build/manifest.json && echo "前端构建完成"
find public/build -maxdepth 1 -type f -printf '%f\n' | head
~~~

npm ci 依据 package-lock.json 安装依赖；生产发布不要使用 npm install 随意改锁文件，也不要执行 npm update。

当前项目不要求启用 Inertia SSR。只有明确启用 SSR 后，才在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
npm run build:ssr
~~~
## 12. 配置宝塔进程守护管理器队列 Worker

项目的图片派生图、OCR、哈希和相似候选等耗时任务必须异步执行，不能依赖用户请求同步完成；智能标签当前已停用。

### 12.1 在项目根目录验证队列环境

~~~bash
cd /www/wwwroot/img.leomessi.cn
redis-cli ping
/www/server/php/83/bin/php -m | grep -i '^redis$'
/www/server/php/83/bin/php -r 'var_dump(function_exists("imagecreatefromstring")); var_dump(function_exists("imagewebp"));'
/www/server/php/83/bin/php artisan queue:failed
~~~

预期结果是 redis-cli 返回 PONG、PHP 模块包含 redis，queue:failed 能够正常执行。

php -m 显示 pcntl 只是扩展已加载，不代表 pcntl_signal() 一定可用。继续检查 CLI 函数：

~~~bash
/www/server/php/83/bin/php -r 'var_dump(extension_loaded("pcntl")); var_dump(function_exists("pcntl_signal")); echo "disable_functions=", ini_get("disable_functions"), PHP_EOL;'
/www/server/php/83/bin/php --ini
~~~

如果 extension_loaded("pcntl") 为 true，但 function_exists("pcntl_signal") 为 false，说明 pcntl 函数被 /www/server/php/83/etc/php-cli.ini 的 disable_functions 禁用了。只修改 CLI 配置，不修改 PHP-FPM 配置，不影响其他网站的 Web 请求。

先备份并移除队列 Worker 所需的禁用项：

~~~bash
cd /www/wwwroot/img.leomessi.cn
cp -n /www/server/php/83/etc/php-cli.ini /www/server/php/83/etc/php-cli.ini.bak.before-queue-pcntl
sed -i \
  -e 's/pcntl_alarm,//g' \
  -e 's/pcntl_signal_dispatch,//g' \
  -e 's/pcntl_signal,//g' \
  /www/server/php/83/etc/php-cli.ini
~~~

cp -n 出现 non-portable warning 只是备份命令的兼容性提示，不是失败。修改后验证：

~~~bash
/www/server/php/83/bin/php -r 'foreach (["pcntl_signal","pcntl_alarm","pcntl_signal_dispatch","pcntl_async_signals"] as $f) printf("%s: %s\n", $f, function_exists($f) ? "可用" : "不可用");'
~~~

四个函数都应显示“可用”。不要因为 pcntl 已加载就跳过函数检查。

### 12.2 在宝塔进程守护管理器中创建 Worker

下面这一步在宝塔面板图形界面中完成，不是在 SSH 当前目录中执行：

1. 打开宝塔“进程守护管理器”。
2. 新增 Laravel Worker，名称填写 imgleomessi-worker。
3. 工作目录填写 /www/wwwroot/img.leomessi.cn。
4. 运行用户填写 www。
5. 命令填写以下完整内容，PHP 路径以 VPS 实测为准。必须使用以 / 开头的绝对路径：

如果日志出现 supervisor: couldn't exec www/server/php/83/bin/php: ENOENT，说明 PHP 路径缺少开头的 /，Supervisor 在 Laravel 启动前就已经找不到可执行文件。不要使用相对路径，也不要在命令中添加 cd、nohup 或末尾的 &。

~~~ini
[program:imgleomessi-worker]
command=/www/server/php/83/bin/php /www/wwwroot/img.leomessi.cn/artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3 --timeout=120 --max-time=3600
directory=/www/wwwroot/img.leomessi.cn
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwlogs/imgleomessi-worker.log
stopwaitsecs=360
~~~

6. 保存配置并启动。
7. 确认状态显示“运行中”。
8. 查看日志，确认没有持续报错。

不要执行 apt install supervisor，不要以系统 supervisorctl 作为本项目管理入口。本 VPS 使用宝塔已安装的基于 Supervisor 的进程守护管理器。

### 12.3 Worker 启动后的验证

首次配置 Worker 时，不要直接依赖宝塔后台判断。先回到 SSH，在项目根目录前台验证：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan queue:work redis --queue=high,default,low --sleep=3 --tries=3 --timeout=120 --max-time=3600
~~~

命令保持运行且没有报错，说明 Worker 可以启动；按 Ctrl+C 停止是正常操作，然后再交给宝塔进程守护管理器启动。

回到 SSH 后，以下命令在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan queue:restart
/www/server/php/83/bin/php artisan queue:failed
test -f /www/wwwlogs/imgleomessi-worker.log && tail -n 100 /www/wwwlogs/imgleomessi-worker.log
~~~

发布新代码后，先在项目根目录执行 queue:restart，再在宝塔进程守护管理器中重启 imgleomessi-worker。

只有确认失败原因并准备重新处理时，才在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan queue:retry all
~~~

不要把 queue:retry all 当作日常部署命令。

队列参数约束：

- Worker timeout=120。
- Redis retry_after=150。
- timeout 必须小于 retry_after。
- 初次上线使用 numprocs=1，观察 CPU、内存和失败任务后再扩容。

## 13. 配置 Laravel 定时任务

### 13.1 宝塔计划任务配置

下面这一步在宝塔“计划任务”图形界面中完成，不是在 SSH 当前目录中执行：

1. 新增 Shell 任务。
2. 周期选择每分钟。
3. 脚本内容填写：

~~~bash
cd /www/wwwroot/img.leomessi.cn && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1
~~~

不要直接编辑宝塔生成的系统 crontab。

### 13.2 手动验证调度命令

需要手动测试时，在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan schedule:run
~~~

没有输出不一定代表失败；同时检查 Laravel 日志和宝塔计划任务执行记录。
## 14. 配置腾讯云 COS / 数据万象

部署完成、数据库迁移完成并能登录生产后台后，在网页中完成以下配置；这一步不需要在 SSH 项目目录执行命令：

1. 进入生产网站后台“系统设置 → 存储与处理”。
2. 存储方式选择“腾讯云 COS”。
3. 填写 SecretId、SecretKey、地域和存储桶。
4. 开启数据万象处理开关。
5. 如已配置 CDN，填写展示图/CDN 域名。
6. 保存配置。
7. 使用“检测 COS / 数据万象”或“上线前检查”。
8. 验证图片时，必须选择已经存在于当前 COS 存储桶中的业务原图，不能选择只存在于本地的历史图片。

检测范围包括 COS 临时写入、读取、删除，代表性原图是否存在，展示图和缩略图对象是否存在，数据万象标签接口是否可用，以及 CDN 地址格式是否正确。

本检查不会自动迁移本地历史图片，也不会删除 COS 文件或切换生产流量。生产环境图片存储切换、历史图片迁移和 CDN 防盗链必须分别确认。
## 15. 上线前检查清单

### 15.1 命令行统一入口

下面的命令全部在 Laravel 项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
pwd
test -f artisan && echo "Laravel 项目根目录正确"
test -f .env && echo ".env 存在"
test -f vendor/autoload.php && echo "Composer 依赖存在"
test -f public/build/manifest.json && echo "前端构建产物存在"
/www/server/php/83/bin/php -v
/www/server/php/83/bin/php artisan about
/www/server/php/83/bin/php artisan migrate:status
redis-cli ping
/www/server/php/83/bin/php artisan queue:failed
~~~

如果 pwd 不是 /www/wwwroot/img.leomessi.cn，先执行 cd /www/wwwroot/img.leomessi.cn，不要在 /root、/www/wwwroot 或 public 目录执行 Artisan、Composer、npm 和 Git 项目命令。

### 15.2 应用和后台

- APP_ENV=production。
- APP_DEBUG=false。
- APP_KEY 已配置且不再重复生成。
- 尚未使用测试账号；完成迁移后必须执行 app:create-admin 创建独立生产管理员。
- 生产环境可登录 /admin。
- 管理员具备双因素认证。
- 后台生产访问策略已通过验证。

### 15.3 Web 和 PHP

- Nginx 根目录是 /www/wwwroot/img.leomessi.cn/public。
- HTTPS 正常，HTTP 自动跳转 HTTPS。
- PHP-FPM 使用 PHP 8.3。
- PHP CLI 与宝塔进程守护管理器 Worker 使用同一套 PHP 8.3。
- PHP 扩展完整；若生产选择 local 存储，PHP CLI 必须可用 GD 和 WebP（`imagecreatefromstring`、`imagewebp`）。
- 上传限制满足实际批量上传需求。
- storage 和 bootstrap/cache 可由 www 写入。

### 15.4 数据库、缓存和队列

- MySQL 使用独立数据库用户。
- Redis 返回 PONG。
- CACHE_STORE=redis。
- SESSION_DRIVER=redis。
- QUEUE_CONNECTION=redis。
- 宝塔进程守护管理器 Worker 状态为运行中。
- Worker 日志没有持续报错。
- queue:failed 无异常积压。

### 15.5 图片链路

- 后台存储模式已选择 COS。
- COS / 数据万象检测通过。
- 上传一张测试图片后，原图写入 COS。
- 展示图和缩略图生成成功。
- SHA-256、按需 OCR 和相似候选任务状态符合预期；智能标签不再作为当前生产任务。
- 发布图片后首页、图库列表和详情页可见。
- 未发布图片不会出现在前台。
- 当前没有误迁移或误删除本地历史图片。
## 16. 正式发布顺序

下面是一份从已经拉取代码的 VPS 继续执行的顺序。除宝塔面板操作外，命令全部在 /www/wwwroot/img.leomessi.cn 执行：

1. 在宝塔面板创建生产数据库和独立数据库用户。
2. 在项目根目录创建并填写 .env。
3. 在项目根目录确认 APP_KEY，仅首次初始化时生成。
4. 在项目根目录安装 Composer 依赖。
5. 在项目根目录执行数据库迁移。
6. 如果首次部署或本次发布包含前端代码变化，按第 11 节在项目根目录执行 npm ci 和 npm run build；不需要执行两遍。
7. 在项目根目录重建 Laravel 缓存。
8. 在宝塔网站设置中把运行目录设置为 /www/wwwroot/img.leomessi.cn/public。
9. 在宝塔进程守护管理器中创建并启动 Worker。
10. 在宝塔计划任务中配置每分钟 schedule:run。
11. 登录生产后台，配置 COS / 数据万象并执行检测。
12. 完成命令行和网页验收后，再开放正式流量。

### 16.1 后续版本从 GitHub 发布到 VPS

适用场景：本地已经完成 Bug 修复或功能开发，代码已经提交并推送到 GitHub 的 main 分支；生产环境只负责拉取已确认的代码，不在 VPS 直接修改业务代码。

所有 SSH 命令都在 VPS 项目根目录执行。每次重新登录 SSH 后先执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
pwd
git status --short
~~~

如果 git status --short 显示生产环境手工修改过受 Git 跟踪的代码，先停止发布并备份差异；不要使用 git reset --hard 覆盖它们。well-known 等未跟踪的 SSL 文件只要不与仓库文件冲突，可以保留。

拉取 GitHub 最新 main：

~~~bash
cd /www/wwwroot/img.leomessi.cn
export GIT_SSH_COMMAND='ssh -o IdentitiesOnly=yes -i /root/.ssh/imgleomessi_vps_deploy'
git fetch origin main
git checkout main
git pull --ff-only origin main
git log -1 --oneline
~~~

命令含义：

- git fetch origin main：只从 GitHub 获取 main 的最新提交，不修改当前网站文件；
- git checkout main：确认当前部署分支是 main；
- git pull --ff-only origin main：只允许无分叉的快进更新，不会自动制造合并提交；
- git log -1 --oneline：确认 VPS 当前实际使用的提交号。

安装 PHP 依赖并发布 Filament 后台资产：

~~~bash
cd /www/wwwroot/img.leomessi.cn
composer install --no-dev --prefer-dist --optimize-autoloader
/www/server/php/83/bin/php artisan filament:upgrade
/www/server/php/83/bin/php artisan filament:cache-components
~~~

composer install 按 composer.lock 安装生产依赖；不要执行 composer update。filament:upgrade 重新发布后台 CSS/JavaScript 并清理 Filament 资产相关缓存；filament:cache-components 重新发现后台资源、页面和 Livewire 组件。

如果本次发布修改了 resources/js、resources/css、vite.config.ts、package.json 或 package-lock.json，再在项目根目录执行前端构建；只修改 PHP、迁移、后台资源类或文档时不需要重复构建：

~~~bash
cd /www/wwwroot/img.leomessi.cn
npm ci
npm run build
test -f public/build/manifest.json && echo "Inertia 前端构建完成"
~~~

执行数据库迁移和应用缓存刷新：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan migrate --force
/www/server/php/83/bin/php artisan optimize:clear
/www/server/php/83/bin/php artisan filament:upgrade
/www/server/php/83/bin/php artisan filament:cache-components
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan route:cache
/www/server/php/83/bin/php artisan view:cache
~~~

migrate --force 只执行尚未执行的迁移；optimize:clear 清除旧配置、路由、视图和框架缓存；后三条 cache 命令重新生成生产缓存。Filament 两条命令要在 config:cache、route:cache 和 view:cache 之前执行。

发布完成后重启 PHP-FPM、队列 Worker 和队列状态：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan queue:restart
/www/server/php/83/bin/php artisan queue:failed
git status --short
~~~

然后在宝塔面板中：

1. 重启本项目使用的 PHP 8.3 PHP-FPM；
2. 在“进程守护管理器”中重启本项目 Worker；
3. 确认 Worker 日志没有启动错误；
4. 浏览器使用 Ctrl+F5 后检查首页、登录、/admin、/admin/photos 和系统设置；
5. 如果登录状态异常，先按“后台登录循环”章节检查会话，不要重复创建管理员。

生产发布禁止执行：

~~~bash
git reset --hard
git clean -fd
composer update
php artisan db:seed --force
php artisan key:generate --force
~~~

前两条可能删除生产文件，composer update 会改变锁定依赖，完整 Seeder 会创建开发测试数据，重新生成 APP_KEY 会使现有会话和加密数据失效。
## 17. 回滚方案

如果新版本出现严重错误：

1. 停止新增上传和发布操作。
2. 保留错误日志、Worker 日志和当前版本信息。
3. 在宝塔面板进入维护状态或暂时限制站点访问。
4. 停止宝塔进程守护管理器中的当前 Worker。
5. 恢复上一份代码、.env 和 public/build。
6. 在项目根目录重建缓存：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan optimize:clear
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan route:cache
/www/server/php/83/bin/php artisan view:cache
~~~

7. 在宝塔面板重启 PHP-FPM 和 Worker。
8. 在项目根目录检查首页、登录、后台和已有图片。
9. 只有确认数据库迁移导致不可逆问题时，才根据备份恢复数据库。

不要重新生成 APP_KEY，不要直接删除生产数据库，不要使用 git reset --hard 覆盖未备份的生产文件。
## 18. 常见故障排查

### 首页 500

以下命令在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan optimize:clear
tail -n 200 storage/logs/laravel.log
~~~

检查 PHP 版本、扩展、.env、数据库连接和文件权限。

### 后台登录失败或无权限

先在项目根目录确认环境：

~~~bash
cd /www/wwwroot/img.leomessi.cn
grep -E '^(APP_ENV|APP_DEBUG|APP_URL)=' .env
/www/server/php/83/bin/php artisan optimize:clear
~~~

然后检查生产后台访问策略、管理员账号状态、APP_ENV 和 Filament 面板授权逻辑。不要使用 test@example.com 或开发环境账号。

### 队列不处理

以下命令在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
redis-cli ping
/www/server/php/83/bin/php artisan queue:failed
tail -n 200 /www/wwwlogs/imgleomessi-worker.log
~~~

同时在宝塔进程守护管理器中查看 Worker 状态和日志。重点检查 PHP CLI 的绝对路径、pcntl 函数是否被 disable_functions 禁用、Redis PHP 扩展、运行用户、项目目录权限和 QUEUE_CONNECTION。如果出现 child process was not spawned，先检查命令第一个路径是否为 /www/server/php/83/bin/php。

### 数据库连接或迁移失败

以下命令在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME)=' .env
/www/server/php/83/bin/php artisan migrate:status
~~~

不要在命令行参数中直接写数据库密码。检查宝塔数据库用户名权限、数据库名称、MySQL 是否运行和 .env 是否保存成功。

### 后台登录循环：输入账号密码后又回到登录页

如果新浏览器输入正确账号密码后页面刷新并再次显示登录页，说明登录后的 Laravel session cookie 没有被下一次请求带回，优先检查生产 .env 的会话配置，而不是重新创建管理员账号。

先在项目根目录查看 Laravel 实际读取的会话配置。不要使用 Tinker：如果 PHP CLI 的 disable_functions 包含 shell_exec，Tinker 依赖的 PsySH 会直接报错；这不代表网站运行失败，也不需要为了 Tinker 恢复 shell_exec。

以下命令不依赖 Tinker，也不会打印 APP_KEY、数据库密码或 Redis 密码：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan about --only=Environment
/www/server/php/83/bin/php artisan config:show session
grep -E '^(APP_URL|SESSION_DRIVER|SESSION_COOKIE|SESSION_DOMAIN|SESSION_PATH|SESSION_SECURE_COOKIE|SESSION_HTTP_ONLY|SESSION_SAME_SITE|SESSION_PARTITIONED_COOKIE|REDIS_HOST|REDIS_PORT)=' .env
awk -F= '/^APP_KEY=/{print "APP_KEY_length=" length($2)}' .env
~~~

生产单域名建议在 .env 中明确使用以下配置。域名必须替换成实际访问后台的正式 HTTPS 域名：

~~~dotenv
APP_URL=https://img.leomessi.cn

SESSION_DRIVER=redis
SESSION_COOKIE=imgleomessi_session
SESSION_DOMAIN=null
SESSION_PATH=/
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false
~~~

修改 .env 后，在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
chown www:www .env
chmod 640 .env
/www/server/php/83/bin/php artisan optimize:clear
/www/server/php/83/bin/php artisan config:cache
~~~

确认 PHP 端实际可以写入和读取 Redis；以下命令不依赖 Tinker，只写入一个临时测试键并立即删除：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php -r 'require "/www/wwwroot/img.leomessi.cn/vendor/autoload.php"; $app=require "/www/wwwroot/img.leomessi.cn/bootstrap/app.php"; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $cache=$app->make("cache")->store("redis"); $key="imgleomessi:deploy-session-check"; $cache->put($key,"ok",60); echo $cache->get($key).PHP_EOL; $cache->forget($key);'
~~~

预期输出 ok。如果此命令报 Redis 认证、连接或权限错误，先修复 PHP 的 Redis 连接配置；redis-cli ping 只能证明命令行客户端能连通，不能完全证明 Laravel PHP 端的 Redis 配置正确。

再检查 HTTPS 响应是否下发 session cookie：

~~~bash
cd /www/wwwroot/img.leomessi.cn
curl -skD - -o /dev/null https://img.leomessi.cn/admin/login | grep -iE '^(HTTP/|set-cookie:|location:)'
~~~

应看到 Set-Cookie，并且 cookie 名称是 imgleomessi_session。如果没有 Set-Cookie，检查 PHP-FPM 的 .env 缓存、站点 HTTPS 配置和 Laravel 日志。

完成服务器修复后，清除旧浏览器中本站的 Cookie，或直接使用新的无痕窗口重新登录。不要只按 Ctrl+F5：Ctrl+F5 不会删除旧 session cookie。

本次生产环境已确认的根因与修复：

- `config:show session` 曾显示 `cookie = -session`；项目 `APP_NAME` 是中文“梅西图片档案库”，Laravel 原默认值通过 `Str::slug(APP_NAME)` 生成 Cookie 名称，中文被转为空字符串后就得到 `-session`。
- Redis PHP 写入/读取已经验证正常，因此本次登录循环不是 Redis 服务不可用，也不是管理员账号不存在。
- 项目代码已将 Cookie 默认值固定为 ASCII 名 `imgleomessi_session`，`.env.example` 也已补齐同名配置；VPS 拉取包含该修复的版本后，即使漏填 `SESSION_COOKIE` 也不会再回退为 `-session`。
- 生产环境的 `APP_DEBUG` 必须为 `false`。`APP_DEBUG=true` 会在异常页面暴露路径、配置错误和堆栈信息，不能作为登录问题的解决办法。

VPS 拉取包含该修复的版本后，以下命令全部在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
export GIT_SSH_COMMAND='ssh -o IdentitiesOnly=yes -i /root/.ssh/imgleomessi_vps_deploy'
git pull --ff-only origin main
~~~

然后编辑 `/www/wwwroot/img.leomessi.cn/.env`，确认或补充以下生产配置；数据库、COS、Redis 等其他已有配置不要覆盖：

~~~dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://img.leomessi.cn

SESSION_DRIVER=redis
SESSION_COOKIE=imgleomessi_session
SESSION_DOMAIN=null
SESSION_PATH=/
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false
~~~

保存 `.env` 后仍在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
chown www:www .env
chmod 640 .env
/www/server/php/83/bin/php artisan optimize:clear
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan about --only=Environment
/www/server/php/83/bin/php artisan config:show session
~~~

预期结果：Environment 为 `production`、Debug Mode 为 `DISABLED`，Session Cookie 为 `imgleomessi_session`，Session Driver 为 `redis`。之后在宝塔重启本网站 PHP 8.3 PHP-FPM，并清除浏览器本站 Cookie；旧的 `-session` Cookie 不会自动变成新名称，必须删除或使用无痕窗口重新登录。
### 前台与后台登录入口验收

1. 前台访问 `/login` 登录成功后应进入 `/me`，不能再进入 Laravel Starter Kit 的 `/dashboard` 占位页面。
2. `admin` / `editor` 且状态正常的账号，在 `/me` 的个人中心菜单中显示“管理后台”，点击后进入 Filament `/admin`。
3. 普通用户在个人中心只看到“退出登录”，不显示管理后台入口。
4. 未登录用户直接访问 `/admin` 时进入 `/admin/login`；`/admin/login` 是后台直达入口，不替代前台 `/login`。
5. 旧地址 `/dashboard` 仅作为兼容地址，认证用户访问后应跳转 `/me`。
### 登录后台后白屏、没有菜单或设置入口

如果 /admin/login 登录成功，顶部能看到站点名称和头像，但主体区域全白、没有左侧菜单或“系统设置”，这通常不是账号密码问题：登录授权已经通过，优先按“Filament 资产未发布、组件缓存过期、站点运行目录不正确”排查。

以下命令全部在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan optimize:clear
/www/server/php/83/bin/php artisan filament:upgrade
/www/server/php/83/bin/php artisan filament:cache-components
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan route:cache
/www/server/php/83/bin/php artisan view:cache
test -f public/css/filament/filament/app.css && echo "Filament CSS 存在"
test -f public/js/filament/filament/app.js && echo "Filament JS 存在"
test -f bootstrap/cache/filament/panels/admin.php && echo "Filament 组件缓存存在"
~~~

然后在宝塔网站设置中确认：

1. 网站运行目录是 /www/wwwroot/img.leomessi.cn/public，不是项目根目录；
2. Nginx 配置没有把 /css/filament/、/js/filament/ 或 /livewire/ 指向其他目录；
3. 保存后重载 Nginx，并在浏览器按 Ctrl+F5 强制刷新；必要时退出后台后重新登录。

如果仍然白屏，在项目根目录执行下面的只读检查：

~~~bash
cd /www/wwwroot/img.leomessi.cn
curl -I https://img.leomessi.cn/css/filament/filament/app.css
curl -I https://img.leomessi.cn/js/filament/filament/app.js
LIVEWIRE_URL="$(curl -sk https://img.leomessi.cn/admin/login \
  | grep -oE 'https://img.leomessi.cn/livewire-[^"]+/livewire(\.min)?\.js[^"]*' \
  | head -n 1)"
echo "$LIVEWIRE_URL"
test -n "$LIVEWIRE_URL" && curl -I "$LIVEWIRE_URL"
tail -n 200 storage/logs/laravel.log
~~~

Filament CSS、Filament JS 和上面动态发现的 Livewire JS 都应返回 200；Livewire JS 如果返回 404，先修正宝塔伪静态规则中的 `location ^~ /livewire-`，不要先重建管理员账号或重新生成 APP_KEY。资源都返回 200 但页面仍白屏时，再查看浏览器开发者工具 Console/Network 中的 JavaScript 或 Livewire 请求错误，并同时检查 Laravel 日志。不要执行完整 DatabaseSeeder。

### 前端页面空白或资源 404

以下命令在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
test -f public/build/manifest.json && echo "构建产物存在"
npm run build
~~~

然后确认宝塔网站运行目录是 /www/wwwroot/img.leomessi.cn/public，不是项目根目录。

### 其他前台路由返回 Nginx 404

如果首页可以打开，但其他前台路径显示 Nginx 的 404 Not Found，按以下顺序排查：

1. 在宝塔网站设置中确认运行目录是 /www/wwwroot/img.leomessi.cn/public，不是项目根目录。
2. 确认伪静态中存在 try_files $uri $uri/ /index.php?$query_string;。
3. 保存配置后重载 Nginx。
4. 在项目根目录检查 Laravel 路由：

~~~bash
cd /www/wwwroot/img.leomessi.cn
/www/server/php/83/bin/php artisan route:list
~~~

5. 用浏览器或 curl 验证 /photos、/albums、/search 等路径。若仍是 Nginx 404，查看宝塔站点配置和 Nginx 错误日志；若变成 Laravel 页面或 Laravel 500，则说明伪静态已经生效，应继续按 Laravel 日志排查。

~~~bash
curl -I https://img.leomessi.cn/photos
curl -I https://img.leomessi.cn/albums
~~~

预期不再返回 Nginx 404；具体状态码可能是 200、302 或需要登录时的其他 Laravel 响应。
### 图片上传成功但首页不显示

检查图片是否已发布、是否完成必选分类、展示图/缩略图是否生成、COS 对象是否存在、CDN 域名是否正确，以及前台是否读取了当前配置的存储地址。需要查看 Laravel 日志时，在项目根目录执行：

~~~bash
cd /www/wwwroot/img.leomessi.cn
tail -n 200 storage/logs/laravel.log
~~~

### COS / 数据万象失败

检查 SecretId、SecretKey、地域、存储桶、数据万象绑定关系和 COS 原图 Key。后台检测必须选择当前 COS 存储桶中已经存在的原图。不要把密钥写入 SSH 命令、日志或 Git。
## 19. 当前未执行事项

截至本手册整理时，以下操作仍未执行：

- 生产数据库已创建并完成首次迁移；后续版本迁移仍需按发布流程执行。
- 生产 `.env` 已创建，并已验证 Laravel 实际读取到正确的数据库配置；腾讯云凭证仍需在生产后台填写。
- 已在宝塔“进程守护管理器”中配置并验证 Laravel Worker 正常启动；后续发布仍需按本手册执行 queue:restart 和日志检查。
- 尚未完成全套生产上线验收。
- 未配置正式域名和 HTTPS。
- 未执行历史图片迁移。
- 未配置 CDN 防盗链。
- 未进行正式生产流量切换。

已完成但不等于生产上线的准备工作：

- 本地项目已完成首次 Git 提交并推送到 GitHub `main`。
- VPS 已使用独立只读 Deploy Key 将 GitHub `main` 拉取到 `/www/wwwroot/img.leomessi.cn`，并保留 SSL `.well-known`。
- PHP CLI 重复扩展、Composer `putenv()` 和 Composer 版本问题已完成修复与验证。

- 宝塔 Worker 已使用 PHP CLI 和 Artisan 的绝对路径，并完成 pcntl 函数和 Supervisor 启动验证。

这些操作必须在生产后台访问策略、管理员账号、备份和回滚方案确认后，按本手册逐项执行。
