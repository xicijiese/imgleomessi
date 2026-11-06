# Changelog

## 2026-10-22

### Changed

- 收窄桌面导航搜索框、放大导航文字并统一中等屏幕折叠断点；首页分享移为右下悬浮工具条，桌面滚动后提供返回顶部，手机保留紧凑分享按钮，移除头图下原分享行。

- 游客导航合并为单一登录入口，注册入口移至登录页；登录、注册改为蓝白档案风格的响应式布局，沿用站点 LOGO，增加密码显隐、字段错误关联和提交状态，保留现有认证规则。

- 收口网站/小程序统一账号完整方案：PRD新增首次登录/同号冲突/已绑定微信优先决策表、昵称头像保留与主动编辑同步、无密码网页登录衔接、可信标识/解绑边界和W01—09网站改动清单；页面地图补充相应表单/状态。合并此前修订与旧必填方案的冲突表述，不再依靠覆盖注释决定实现。
- 数据、架构、主进度、任务镜像、平台集成与发布回退同步；明确实际email/password非空、可空迁移待做、手机号字段已存在、历史值不自动认证、绑定不修改旧资料/角色/权益。仅文档修改，未执行认证代码、迁移或生产操作；本次文档一致性验证不作为账号功能测试通过。
- 修订统一账号计划：平台优先，小程序主动微信登录即注册，不强制邮箱/密码，手机号独立授权验证、昵称头像选择填写；网站适配手机号或邮箱加密码及无密码账号安全设置密码。原账号验证/2FA保留，不将手机号作永久主键，不按历史未验证号码合并。
- 核对已有phone字段与注册输入框、Fortify仍仅邮箱登录及实际email/password非空；计划补充可空字段、验证来源/时间、找回/解绑/后台兼容与专项测试。MP-04估算9—13日，总计45—66日加20%缓冲。同步现有文档，未改认证代码、迁移、权限或生产。
- 微信官方网页不可读后，以官方api-typings及头像昵称组件核实登录凭证/手机号授权事件/资料填写相互独立；新项目手机号接口资格/额度/费用仍待实际后台核验。

### Fixed

- 修复文字搜索遗漏相册标题、赛季等分类词和标签名：图片查询补齐公开关联资料，相册查询补齐分类名；保持筛选交集、关联去重和隐藏/版权边界。修正本地演示提示词及Seeder默认来源，保留已上传双LOGO；搜索专项30项/645断言、全量360项/4092断言及真实本地HTTP验证通过，无需迁移，未部署生产。

- 修复首页导航变白后白色LOGO对比不足：后台增加浅色版LOGO上传项，原上传项明确为深色默认版；桌面/移动导航按既有背景状态切换，菜单打开同步使用深色，返回顶部恢复浅色。兼容旧单图配置，预加载默认图，无需迁移。用户两张实际图片尚待后台上传。全量354项PHP测试/3978断言、6项导航组件交互测试、类型/ESLint/构建通过；浏览器实图验收受本机沙箱启动错误阻断。

## 2026-10-21

### Changed

- 补齐并经用户确认微信小程序基础版20页/4入口方案，列明功能、布局、流程、权限和异常状态；保存展示图与本期无投稿按确认执行。统一“基础版”名称及页面入口，进入开发计划阶段，不把文字规格写成已交付客户端。
- 用户确认新注册独立小程序（尚未注册，与旧已认证项目无关）、微信原生TypeScript、20页首版分切片验收后统一上线、沿用网站注册资料与一对一微信绑定/安全解绑、游客保存，以及好友卡片/小程序码海报/归因与50人勋章，无固定上线日期。
- 完整开发计划合并至进度主文档第9节：MP-00—09、拟定API、增量身份/令牌数据、安全/媒体/分享规则、42—61开发日估算及额外缓冲、测试矩阵、平台前置、发布兼容与回滚；同步现有PRD、页面、架构、数据库、集成、部署、领域和任务镜像。仅完成计划，未生成客户端/迁移、安装依赖或操作平台/生产。
- 计划核对代码后明确：现有举报仅针对公开讨论评论，图片版权投诉走现有下架联系说明；小程序归因需要适配Web Cookie/Session机制。微信官方网页本次无法读取，当前主体/类目/隐私/接口参数保留为注册后核验门槛，不声称已验证全部平台规则。

## 2026-10-20

### Added

- 虎皮椒微信真实赞助本地流程：订单/凭据快照、验签幂等入账、期限与最高等级重算、整单退款及权益撤回、7/1天站内提醒、真实报表/CSV、后台支付配置和待核查操作。收款默认关闭；xunhuweb 特定查询/退款及嵌套响应协议尚需渠道样本适配与实际资金联调，未部署生产、未真实扣款。
- 新增支付迁移在本地备份后执行，原用户/图片/勋章/订单/支持资料保持不变；前台移除模拟付款按钮和公开金额，后台不再直接编辑真实状态。全量352项测试/3949断言及19项支付专项通过，类型、ESLint、构建和本地数据库队列补单验证通过。

- 网站分享推广：四类内容统一弹层、个人短链接、二维码、队列PNG海报、可见10秒与服务端滚动凭证、账号/游客全站首次有效归因和长期去重、50人推广勋章。
- 个人中心 /me/shares、管理员 /admin/share-promotions 四页签、归因纠正与审计、系统分享配置、90天明细/海报清理及隐私说明。新增七张表，本地备份迁移后原用户/图库/勋章状态不变。
- 真实本地队列海报生成、后台处理服务连接和浏览器页面已验证；全量333项/3822断言，初轮分享专项20项/208断言（页面简化后20项/206断言），类型检查、ESLint和构建通过。公众号无权限，自定义微信卡片暂缓；生产未操作。

- 用户投稿完整闭环：投稿者申请与管理员审批、个人中心私有上传（单图 5MB）、分类/相册/标签、逐张与批量审核、队列入库、高清替换、50 张采纳勋章和匿名/自愿署名。
- COS 可选独立私有投稿桶、上传前匿名探针、对象版本清理；新增四张表、定时清理、事件审计和真实队列验收。全量 313 项测试 / 3614 条断言通过，本地迁移及浏览器已验证，生产未操作。


### Fixed

- 简化个人中心分享推广页：移除重复的“我的分享链接”列表及分页，停止返回对应明细；统一通过现有分享按钮选择渠道。对应PRD、页面地图、架构、领域术语、任务镜像和生产更新教程已同步；专项20项/206断言及前端检查通过。

- 投稿记录为空或只有一页时隐藏分页；图片投稿审核、投稿资格申请补齐与既有后台导航一致的线框图标。投稿专项 28 项/188 条断言、类型检查、ESLint、构建及浏览器验证通过。

### Changed

- 同步虎皮椒接口地址核对及不停站生产发布教程：明确同网关三个操作共用凭据；当前查询/退款仍需手填，自动处理待开发，响应适配和真实资金联调待完成。仅更新现有文档，未修改支付代码、商户配置或生产环境。

- 完成主进度、PRD、领域术语、页面地图、架构、数据模型、部署说明和任务镜像同步；纠正三角色、勋章待开发和投稿勋章待开放旧口径。剩余按高级分享、真实支付、小程序与后置搜索评估分类，取消项不列待办；生产状态不据本地验证推定。
- 勋章大厅移入个人中心 `/me/badges/hall`，复用个人中心侧栏并移除顶部独立入口；旧地址自动跳转，未登录用户进入登录页。

## 2026-10-19

### Fixed

- 相似候选、分组、处理进度和归档记录合并为“相似图管理”同页四标签，旧链接兼容跳转，人工审核即时同步分组；阻止重复提交同类在途任务。
- 修复本地无队列 Worker 导致任务始终排队的运行环境问题；已启动本地 Worker 并完成用户原分析任务。增加连接/队列心跳、离线提示和真实队列消费测试；本地开发命令使用长驻 Worker 及 120 秒处理超时。
- 本轮全量回归：272 项测试、3281 条断言通过；浏览器确认原任务已完成，34 张图片分析后没有符合阈值的相似组；没有执行图片归档。

- 补齐当前本地 SQLite 尚未执行的相似图模块迁移，修复后台相似图分组等页面缺表 500。迁移前完成私有数据库备份，仅新增 5 张表，未启动分析或自动归档；本地管理员访问相关 4 个页面均返回 200，专项回归 34 项、186 条断言通过。生产未操作。

### Added

- 勋章运营增强：公开大厅、图片/GIF、多枚独立佩戴、北京时间纪念日自动获取；后台统一配置、批量发放进度及授予/撤销/恢复记录。
- 批量发放保存接收者快照，真实队列分块执行并支持幂等重试；已撤销记录不自动重发，已授予勋章不可删除。保留原有获得和佩戴状态，默认不自动佩戴新勋章。
- 本地增量迁移已备份执行，285 项测试 / 3419 条断言通过。投稿/推广 50 门槛明确待开放，取消勋章排行榜和通用活动编辑器。本轮未部署生产。

- 相似图与去重模块：详情页视觉相似推荐、后台异步分析分组与人工拆分、SHA-256 精确重复图归档和恢复、以图搜图及私有临时文件清理。
- 新增分组、处理进度、归档日志后台入口及数据库迁移；队列执行时校验管理员权限，批量处理不删除原图或合并互动。

### Changed

- 确认以文搜图为已完成的普通文字检索；按功能模块推进后续任务，不引入 AI 语义搜索。

### Verified

- 全量测试 268 项、3250 条断言通过；vue-tsc、ESLint、生产构建和 diff 检查通过。
- 隔离数据库桌面/手机页面检查与后台图片比对通过，预览服务和数据已清理；生产未迁移、未部署，真实图库尚未执行批量处理。

## 2026-09-13

### Fixed

- 修复图片详情页收藏和点赞请求在生产环境因 XSRF-TOKEN URL 编码或会话过期返回 419 的问题：前端发送 URL 解码后的 X-XSRF-TOKEN，首次 419 时刷新页面令牌并自动重试一次，避免已登录用户被误导向登录页或个人中心。

### Verified

- php artisan test --compact：251 个测试、3112 条断言通过。
- npx.cmd vue-tsc --noEmit：通过。
- npm.cmd run build：通过。

## 2026-09-12

### Added

- 完成管理员用户治理与用户头像切片：用户可在 `/settings/profile` 上传、替换和移除头像，管理员可在 `/admin/users` 代维护头像。
- 新增用户手机号（注册必填且唯一）、角色调整、启用/停用、管理员在编辑窗口直接重置密码、强制退出全部会话、批量用户状态操作、登录历史和管理员操作审计；重置密码不发送邮件，保留三角色模型并保护活动档案共建者授权链路与最后一名有效管理员。
- 新增用户头像存储键、会话版本、最近登录、登录历史和审计日志数据库结构；头像复用当前 local/COS 存储，不暴露存储键。

### Verified

- `php artisan test --compact`：251 个测试、3112 条断言通过。
- `npx.cmd vue-tsc --noEmit`：通过。
- `npm.cmd run build`：通过。

## 2026-09-12

### Changed

- 完成阶段 2A 用户管理与档案共建者授权基础：新增 contributor_profiles、管理员授予/编辑/撤销操作和安全角色恢复规则。
- 收紧 Filament 后台授权：普通用户不能进入后台，editor 仅能维护图片、相册、分类和标签；用户、评论、赞助、支付、系统设置和其他管理员资源仍由 admin 控制。
- 移除本地/测试环境后台访问绕过，并将既有后台测试改为显式使用 active admin 账号；全量测试 237 个通过、2893 条断言通过。
- 同步更新产品、数据库、架构、页面地图、开发进度和本地任务文档；阶段 2B 前台共建者名单已完成。
- 完成阶段 2B 档案共建者前台展示与资料管理：/supporters 分区展示运营守护者和档案共建者，/admin/users 支持公开开关、贡献方向和展示顺序，公开 payload 不泄露内部授权字段。
- 完成生产环境收口与验收：数据库迁移、依赖与构建、缓存、Supervisor 队列、定时任务、日志告警、备份回滚、COS/CDN 防盗链、历史迁移、正式切流及档案共建者验收均已完成；项目所需的管理员用户治理与头像功能已完成。

## 2026-09-08

### Changed
- 统一前台登录成功后的默认跳转为 `/me`，将旧 `/dashboard` 改为用户中心兼容跳转，避免管理员或普通用户进入 Laravel Starter Kit 空面板；`/admin/login` 继续作为 Filament 后台直达入口。
- 修复生产 Filament/Livewire 动态资源路径被 Nginx 当作静态文件返回 404 的排查与伪静态规则；同时将前台跳转 Filament 的链接改为普通链接，避免 Inertia 与 Livewire 页面切换冲突。
- 补齐前台账号与 Filament 后台权限衔接：服务端共享 canAccessAdmin，管理员 / 编辑从前台个人中心进入 /admin，普通用户不显示后台入口；前台桌面端和移动端均补充退出登录菜单，并增加角色边界回归测试。
- 定位并修复生产后台登录循环：中文站名导致 Laravel 默认 Session Cookie 名称退化为 `-session`；固定默认 Cookie 为 `imgleomessi_session`，补充回归测试，并将 `APP_DEBUG=false`、生产会话配置、旧 Cookie 清理和 PHP-FPM 重启步骤写入部署文档。
- 修复生产后台无默认管理员且生产授权被环境限制的问题：新增 users.role、app:create-admin 命令和 admin/editor 生产访问策略。
- 排查生产后台登录后白屏问题：确认本地 Filament 已发现 18 个资源并生成 8 组菜单，补充生产部署中的 filament:upgrade、filament:cache-components、静态资产 200 检查、运行目录和浏览器强制刷新步骤；当前待在 VPS 执行刷新并完成后台验收。
- 根据生产静态资产 200 但新浏览器登录循环的反馈，补充 Redis session、session cookie、APP_KEY、HTTPS Cookie 属性检查，以及 GitHub 到 VPS 的后续版本升级发布教程；当前不重复创建管理员账号，先完成会话链路排障。
- 根据 VPS PHP CLI 禁用 shell_exec 的实测结果，移除生产排障对 Tinker/PsySH 的依赖，改用 artisan config:show 和不依赖 shell_exec 的 PHP CLI 检查；shell_exec 继续保持禁用。
- 补充生产 Nginx Laravel 伪静态规则和其他前台路由 404 的排查步骤；确认代码路由无需修改。
- 记录生产 Laravel Worker 的 pcntl 函数禁用、前台启动验证、宝塔 Supervisor 绝对路径和日志配置修复；Worker 已验证正常启动。

- 完成本地 GitHub 首次上传：项目位于 `main` 分支，`origin` 使用 SSH 地址 `git@github.com:xicijiese/imgleomessi.git`；`.env` 未被 Git 跟踪，`.env.example` 不含真实凭证。
- 完成 VPS 代码获取演练：VPS 使用独立的只读 Deploy Key，通过 `ssh-keygen` 生成密钥、公钥添加到 GitHub Deploy keys，私钥只留在 VPS；首次连接的主机指纹提示输入 `yes`，不是输入公钥或私钥口令。
- 修正宝塔非空网站根目录的部署流程：不删除 SSL 的 `.well-known`，不再要求空目录克隆；在 `/www/wwwroot/img.leomessi.cn` 直接执行 `git init`、`safe.directory`、`git fetch origin main` 和 `git checkout -b main --track origin/main`，并用 `.git/info/exclude` 保留本地 SSL 目录不参与提交。
- 更新生产部署教程：补充 GitHub 首次提交 / 推送、VPS Deploy Key、非空根目录 Git 接入、`safe.directory`、`.well-known` 保留、`/www/wwwroot/img.leomessi.cn/public` 运行目录和后续 `git pull --ff-only` 流程。
- 根据 VPS 实测结果校正 PHP CLI 状态：恢复 `putenv()`，清理 OPcache / zip 重复加载，Composer 升级到 2.10.3；`php -m` 与 `composer diagnose` 检查通过。
## 2026-09-07

### Changed

- 澄清部署文档中的前端构建命令：npm ci 和 npm run build 只有一套含义，第 11 节是完整步骤，正式发布章节仅引用该步骤，避免重复执行造成误解。
- VPS 生产环境已完成数据库配置校验和首次迁移：Laravel 实际读取 mysql、127.0.0.1、img_leomessi_cn 和 img_leomessi_cn，migrate --force 全部通过；未执行生产 Seeder。
- 记录生产数据库初始化排障：CLI 的 mysql 连接成功但 Artisan 使用 root@localhost、数据库 laravel 和空密码时，先验证 Laravel 实际解析配置，再清理配置缓存并检查 .env 与当前 SSH 环境变量。
- 进一步细化 VPS 首次生产部署：明确 Composer、生产 .env、APP_KEY、数据库迁移、Laravel 缓存、npm 构建、宝塔 Worker、计划任务、COS 配置和验收命令均在何处执行；所有 Artisan、Composer、npm 命令均明确进入 /www/wwwroot/img.leomessi.cn 项目根目录。
- 校正文档状态：主进度和页面地图统一进入“真实生产部署前确认与演练”，同步修正任务区和数据万象真实验证的过期表述；未执行生产切换。
- 修复首页 Welcome.vue 未导入 PublicFooter 导致页脚不渲染的问题；首页专项测试、前端类型检查、生产构建和全量测试均已通过。
- 完成 Ubuntu 24.04 + 宝塔生产部署前准备与上线教程，补充 PHP 扩展、Composer、Node.js、Supervisor、Cron、Redis 队列、COS 配置、备份、回滚和验收清单；标注当前 production 后台访问策略和测试 Seeder 的上线阻塞风险，未执行真实生产部署。
- 根据 VPS 实测结果校正部署教程：宝塔进程守护管理器 3.0.6 统一负责 Worker，不再要求系统 supervisorctl；确认 Redis 可通过 redis-cli 访问；putenv 已恢复，Composer 已升级到 2.10.3，OPcache/zip 重复加载警告当时仍待清理，已在 2026-09-08 的 VPS 实测中完成清理并验证。
- 根据截图补充 PHP CLI 重复扩展的分支修复：区分 PHP 内置模块与其他配置文件来源，避免误删唯一有效的 zip 或 OPcache 配置。
- 完成生产队列上线准备代码：失败任务支持后台异步重新入队和批量重试，处理任务对所有类型使用有限队列重试；数据库/Redis 队列默认 `retry_after=150` 秒，与 120 秒任务超时保持安全间隔。
- 上传批次列表新增待处理、处理中和失败任务统计；修复处理任务与图片联表统计时 `status` 字段歧义导致的后台 500。
- 系统设置新增“上线前检查”，检查当前存储读写、COS 代表性图片的原图/展示图/缩略图对象和 CDN 地址格式；不执行历史迁移、云端删除或生产切流。
- 补充 Supervisor、队列重启、失败任务排障和上线检查部署说明；未连接真实生产环境。
- 本轮定向回归通过：28 个测试，107 个断言。

- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 修复腾讯云数据万象派生图输出 Key 未使用根路径导致的错误：当前 6 张 COS 原图已成功生成展示图和缩略图，SHA-256 已完成；智能标签和相似候选真实任务成功，OCR 对无文字图片返回“未检测到文本”。
- 启动本地数据库队列 Worker，清空本次上传产生的待处理队列；当前队列 pending=0、failed=0。
- 根据产品范围确认，图片质量评分不纳入项目；不新增质量评分任务、接口、后台入口或前台展示，现有 ci_quality_json 仅保留数据库兼容字段。
- 完成智能标签后台手动处理切片：新增 labels 队列任务，调用数据万象 detectLabelProcess 读取 COS 原图并回写 photo_analysis_results.ci_labels_json；后台图片管理支持手动触发、查看、重试和清空。
- 修复 COS / 数据万象检测误选本地图片的问题：检测动作会先确认原图真实存在于当前 COS 存储桶，本地历史图片会明确提示不能用于云端验证。
- 补齐 `league/flysystem-aws-s3-v3` 运行依赖，修复真实 COS 连接缺少 `PortableVisibilityConverter` 类的问题；使用当前后台配置完成一次 COS 临时写入、读取和删除检查。现有 30 张示例图片仍在本地，数据万象真实标签调用待 COS 原图到位后验证。
- 新增系统设置页“检测 COS / 数据万象”入口：管理员选择当前 COS 中已有原图后，可在确认操作后临时验证 COS 读写并调用一次数据万象标签接口；本地历史图片不作为云端验证样本，不自动切换存储、不迁移历史图片。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；当前凭证下 COS 临时读写已通过，数据万象真实标签调用待 COS 原图到位后验证。智能标签后台手动处理已完成，图片质量评分明确不纳入项目。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图、OCR 和智能标签后台辅助切片均已完成，图片质量评分明确不纳入项目，当前进入真实 COS / 数据万象验证收口。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 完成搜索建议 / 热门搜索 / 后台搜索运营配置切片：新增 `search_recommendations` / `search_queries` 表、`SearchRecommendation` / `SearchQuery` 模型、`PublicSearchOperations` 服务、`/search/suggestions` 接口和后台 `/admin/search-recommendations` 搜索运营资源。
- 公共导航搜索框新增防抖搜索建议下拉，点击推荐词或热门词进入 `/search?q=...`，回车搜索行为保持不变。
- `/search` 空关键词或无结果时展示后台启用的推荐搜索词和达到阈值的热门搜索词。
- 搜索记录只用于热门词聚合和排障，不记录 IP / UA，不向前台输出原始日志、筛选 JSON、用户字段或后台内部备注。
- 搜索运营切片未接 Meilisearch / Typesense / Elasticsearch，未改写 `/photos` 与 `/search` 既有数据库筛选语义，未做语义搜索、拼写纠错、搜索广告、相似图或真实数据万象。
- 已完成外部搜索引擎接入必要性评估：当前本地 30 张图片、30 张已发布图片、0 条搜索日志，专项搜索测试 12 个测试 / 240 个断言通过；暂不引入 Meilisearch / Typesense，继续使用 MySQL 站内搜索。下一步在当前 COS 存储桶中放入一张业务原图后完成数据万象真实标签验证。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- `php artisan test tests\Feature\SearchOperationsTest.php tests\Feature\PublicSearchTest.php` 通过：12 个测试，240 个断言。
- `php artisan test` 通过：224 个测试，2778 个断言。
- `npx.cmd vue-tsc --noEmit` 通过。
- `npm run build` 通过。
- `git diff --check` 通过，仅有既有 CRLF/LF 行尾提示。
- `php artisan migrate --force` 通过，已在本地数据库创建 `search_recommendations` / `search_queries` 表。
- 本地服务已启动，`http://127.0.0.1:8000/` 返回 200。
## 2026-09-06

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。
- 完成对手聚合切片：新增 `opponents` / `opponent_photo`、`Opponent` 模型、后台对手管理、图片管理对手关联、`/opponents` 列表和 `/opponents/{slug}` 详情页。
- 对手聚合只展示已发布且版权允许公开的图片；未启用、无公开图片或不存在的对手详情返回 404；未新增比赛表、球队资料库、外部足球 API、比分统计或顶部一级导航入口。
- 整理搜索建议 / 热门搜索 / 后台搜索运营配置开发前确认稿：建议下一切片只增强现有 `/search` 和公共导航搜索建议，不接 Meilisearch / Typesense，不做语义搜索、拼写纠错、个人搜索历史、搜索广告或相似图。

- 完成公开用户个人主页切片：新增 `/users/{user}` 公开页和 `/me/public-profile` 本人公开设置，用户主页默认关闭，需用户主动开启。
- 新增 `users.profile_public`、`users.profile_bio`、`PublicUserProfile`、`PublicUserProfileController`、`Users/Show.vue` 和 `PublicUserProfileTest`；公开页只展示昵称、注册月份、公开简介、公开运营守护者摘要、当前佩戴 / 已获公开勋章和已发布普通评论摘要。
- 公开用户主页继续排除邮箱、手机号、收藏、待审核评论、纠错投稿、举报、通知、订单号、交易号、支付日志、IP、UA、后台备注、敏感词命中和内部审核记录；未新增 `/users` 用户列表、关注、私信、动态流、用户搜索或排行榜。
- 整理对手聚合数据模型开发前确认稿：建议下一切片只做轻量 `opponents` / `opponent_photo` 和 `/opponents` 公开聚合页，不做比赛表、球队资料库、外部足球 API、比分统计或顶部一级导航入口。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified
- `php artisan test tests\Feature\PublicOpponentArchiveTest.php tests\Feature\PublicDimensionArchiveTest.php` 通过：10 个测试，276 个断言。
- `php artisan test` 通过：202 个测试，2639 个断言。
- `npx.cmd vue-tsc --noEmit` 通过。
- `npm run build` 通过。
- `git diff --check` 通过，仅有既有 CRLF/LF 行尾提示。
- `php artisan migrate --force` 通过，已在本地数据库创建 public profile 字段和 opponents / opponent_photo 表。
- 本地 HTTP 检查 `/opponents` 返回 200。

- `php artisan test tests/Feature/PublicUserProfileTest.php` 通过：4 个测试，65 个断言。
- `php artisan test tests/Feature/PublicUserProfileTest.php tests/Feature/UserCenterTest.php tests/Feature/SponsorshipTest.php tests/Feature/BadgeTest.php tests/Feature/PublicSeoTest.php` 通过：25 个测试，384 个断言。
- `npx.cmd vue-tsc --noEmit` 通过。
- `npm run build` 通过。
- `php artisan test` 通过：197 个测试，2535 个断言。

## 2026-09-05

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 完成球队 / 生涯阶段聚合和赛季聚合切片：新增 `/teams`、`/teams/{slug}`、`/seasons` 和 `/seasons/{slug}` 公开页面，基于现有 `career-stage` 与 `season` 分类聚合公开图片和相册。
- 新增 `PublicDimensionArchive`、`DimensionArchiveController`、`Dimensions/Index.vue`、`Dimensions/Show.vue` 和 `PublicDimensionArchiveTest`；详情页支持关键词、年份、普通标签、人物关系标签和事件/发布/热度排序。
- sitemap 已加入 `/teams`、`/seasons` 和有公开内容的维度详情页；本切片未新增对手页、独立足球资料表或顶部一级导航入口。
- 整理公开用户个人主页开发前确认稿：推荐首版采用默认关闭、用户主动开启、`/users/{user}` 轻量公开页，只展示安全公开资料，不做关注、私信、动态流或用户列表。
- 完成时间轴浏览切片：新增 /timeline、/timeline/{year} 和 /timeline/{year}/{month} 公开页面，基于公开图片、相册、专题和 event_date 做年份 / 月份归档。
- 补充时间轴导航入口：默认站点导航、前端公开导航白名单、本地演示 Seeder 和当前本地 site.navigation 设置均已包含并启用 /timeline。
- 整理球队 / 赛季 / 对手聚合开发前确认稿：推荐先基于现有 生涯阶段 和 赛季 分类实现 /teams、/teams/{slug}、/seasons、/seasons/{slug}，对手维度因缺少结构化数据暂不开发，后续先确认数据模型。
- 用户确认 P1-15 后续开发顺序：下一切片选择时间轴浏览，球队 / 赛季 / 对手聚合、公开用户个人主页、外部搜索引擎、相似图、真实云服务、真实支付和小程序继续后置独立确认。
- 整理时间轴浏览开发前确认稿：覆盖 /timeline、/timeline/{year} 和 /timeline/{year}/{month} 的页面范围、数据来源、入口、筛选、排序、状态、权限、交互、UI/UX、性能和验收标准；确认前不开发时间轴代码。
- 整理 P1-15 后续开发顺序确认稿：推荐下一切片为时间轴浏览，并将球队 / 赛季 / 对手聚合、公开用户个人主页、外部搜索引擎、相似图、真实云服务、真实支付和小程序继续列为后续独立确认。
- 修正主进度文档 Phase 2 旧口径：P1-15 已完成数据库内高级组合筛选，未开发项保留为时间轴、资料聚合、外部搜索引擎和相似图能力。
- 完成 P1-15 高级搜索与资料增强：/photos 和 /search 共用高级筛选组件，支持关键词、相册、来源状态、指定来源、版权状态、清晰度、横竖图、水印状态、事件日期、7 个主分类、普通标签和人物同框标签组合筛选。
- 新增 photos.watermark_status 字段和后台图片管理水印状态维护；清晰度和横竖图按图片宽高推导，不允许后台手动乱填；本地演示 Seeder 已补充水印状态并刷新演示数据。
- P1-15 已通过相关功能测试、前端类型检查和生产构建；收口后当前唯一开发入口推进到后续开发顺序确认稿，确认下一切片前不开发时间轴、外部搜索引擎、真实 COS / 数据万象、真实支付、小程序或任何未确认的新切片。
- 完成 P1-14 第三方分享扩展：图片详情页新增分享弹层，支持复制链接、系统分享、微博跳转分享和微信二维码引导；分享记录接口支持 `copy_link`、`native_share`、`weibo`、`wechat_qr` 四类渠道。
- P1-14 明确不接微信 JS-SDK / 真实第三方分享 API、不做分享海报、不做短链接、不做复杂社交治理、不做分享返利、不做下载按钮；P1-15 已完成后当前唯一开发入口推进到后续开发顺序确认稿。
- 修复项目进度口径：将 `docs/project/development-progress.md` 顶部 Phase 1-5 状态同步为已完成 / 基础完成 / 部分完成，并新增“当前未开发功能总览（以此为准）”，避免已完成 P1 功能在早期路线图里仍显示为未开始。
- 同步 PRD、技术架构和 `.scratch/messi-gallery/README.md` 中轻社交、赞助、图片处理与后续待开发功能边界。
- 完成 P1-13 P1 阶段总体验收 / 补漏：新增 P1AcceptanceTest，覆盖公开页面访问、个人中心/settings/后台未登录边界、settings 前台外壳和动态 sitemap/robots。
- P1-13 巡检未发现需要修改业务逻辑的缺口；P1-14 第三方分享扩展现已完成，P1-15 已完成，当前唯一开发入口转入后续开发顺序确认稿。
- 完成 P1-12 sitemap、SEO 基础优化：新增动态 `/sitemap.xml`、动态 `/robots.txt`、公开页面 SEO payload 和前台 `SeoHead` 元信息组件。
- sitemap 只收录公开页面、已发布公开图片、包含公开图片的已发布相册和启用专题；排除后台、个人中心、设置页、认证页、搜索结果、非公开图片、空相册和禁用专题。
- 移除旧 `public/robots.txt` 静态文件，避免本地/生产 Web 服务器直接返回旧规则覆盖 Laravel 动态 robots。
- P1-12 收口后进入 P1-13 P1 阶段总体验收 / 补漏；P1-13 与 P1-14 现已完成，P1-15 已完成，当前唯一开发入口转入后续开发顺序确认稿。
- 进入 P1-12 前补齐前台赞助导航，撤回顶部账号入口里的“我的勋章”独立入口；“我的勋章”保留在个人中心侧栏。
- 修复后台 `/admin/interaction-stats` 互动统计页面 UI，改为标准 Filament Table，避免后台未编译自定义 Tailwind 类时出现裸表格。
- 将后台 `/admin/processing-jobs` 语义统一为“图片入库处理 / 入库处理任务”，用于查看自动处理状态、失败原因和异常补处理，不再作为日常逐图手动创建任务入口。
- 完成 P1-11 图片入库处理与后台重复图提示：新增 `photo_analysis_results` / `processing_jobs` 表、`PhotoAnalysisResult` / `ProcessingJob` 模型、`PhotoProcessingService`、后台 `/admin/processing-jobs` 管理入口和图片管理重复提示。
- 上传图片会自动创建 `metadata` / `hash` 待处理任务；本地演示 Seeder 会根据测试图片生成 60 个基础处理任务结果；P1-11 只做精确 SHA-256 重复提示，不接真实腾讯云数据万象、不做相似图推荐/分组/搜索。
- P1-11 收口后进入 P1-12 sitemap、SEO 基础优化；P1-12、P1-13 与 P1-14 现已完成，P1-15 已完成，当前唯一开发入口转入后续开发顺序确认稿。
- 完成 P1-10 勋章 / 成就基础：新增 `/me/badges` 前台页面、`/admin/badges` 后台管理入口、`badges` / `user_badges` 数据表、`BadgeService` 自动判定服务和 `BadgeSeeder`。
- 个人中心侧栏补齐“我的赞助”和“我的勋章”，顶部账号入口只保留“个人中心”；收藏成功、普通评论审核为已发布、赞助支付成功会同步判定基础勋章。
- 本地演示 Seeder 现在生成用户勋章记录，方便查看 `/me/badges` 整体效果；P1-10 已作为前置阶段收口。
- 完成 P1-9 运营守护者赞助闭环：新增 `/support`、`/support/result`、`/supporters`、`/me/sponsorships` 前台页面，支持赞助方案展示、模拟订单创建、模拟支付成功、个人赞助记录和致谢墙公开展示开关。
- 新增 `/admin/sponsorship-plans`、`/admin/sponsorship-orders`、`/admin/payment-logs` 后台管理入口，支持赞助方案维护、订单状态处理和支付日志排障。
- 新增 `sponsorship_plans`、`sponsorship_orders`、`payment_logs`、`supporter_profiles` 表，并为 `users` 增加 `supporter_until`；P1 阶段只做模拟支付和后台手动处理，不接真实第三方支付 API。
- 新增 `SponsorshipPlanSeeder`，本地演示 Seeder 会生成 4 个模拟运营守护者赞助订单，方便查看支持页、致谢墙和个人中心赞助记录。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- php artisan test tests/Feature/PublicDimensionArchiveTest.php 通过，5 个测试、172 个断言全部成功。
- php artisan test tests/Feature/PublicDimensionArchiveTest.php tests/Feature/PublicTimelineTest.php tests/Feature/PublicPhotoGalleryTest.php tests/Feature/PublicSeoTest.php 通过，20 个测试、595 个断言全部成功。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan test 通过，193 个测试、2470 个断言全部成功。
- php -l database/seeders/LocalGalleryDemoSeeder.php 通过。
- php artisan test tests/Feature/PublicTimelineTest.php tests/Feature/PublicRankingTest.php tests/Feature/SponsorshipTest.php tests/Feature/HomepageSettingsManagementTest.php 通过，22 个测试、376 个断言全部成功。
- php artisan test tests/Feature/PublicRankingTest.php tests/Feature/PublicPhotoInteractionTest.php 通过，15 个测试、174 个断言全部成功。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan test 通过，179 个测试、2027 个断言全部成功。
- php artisan test tests/Feature/P1AcceptanceTest.php 通过，4 个测试、96 个断言全部成功。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- 本地 Web HTTP 巡检通过：公开页面和真实详情页返回 200，个人中心/settings 未登录返回 /login，后台未登录返回 /admin/login。
- php artisan test tests/Feature/PublicSeoTest.php tests/Feature/PublicHomepageTest.php tests/Feature/PublicPhotoGalleryTest.php tests/Feature/PublicPhotoDetailTest.php tests/Feature/PublicAlbumIndexTest.php tests/Feature/PublicAlbumDetailTest.php tests/Feature/PublicTopicIndexTest.php tests/Feature/PublicTopicDetailTest.php tests/Feature/PublicStaticPageTest.php tests/Feature/PublicRankingTest.php tests/Feature/SponsorshipTest.php 通过，49 个测试、1099 个断言全部成功。
- npx.cmd prettier --check 针对 P1-12 前台 SEO 相关 Vue/类型文件通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan route:list --path=sitemap、--path=robots 均确认路由存在。
- 本地 Web 检查通过：`/sitemap.xml` 返回 200 与 XML，`/robots.txt` 返回 200 并包含后台/个人中心/设置/搜索屏蔽规则和 Sitemap 地址。
- php artisan test tests/Feature/PublicRankingTest.php tests/Feature/SponsorshipTest.php tests/Feature/PhotoProcessingTest.php 通过，15 个测试、198 个断言全部成功。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan optimize:clear 已清理 config/cache/compiled/events/routes/views/blade-icons/filament 缓存。
- php artisan route:list --path=support、--path=admin/interaction-stats、--path=admin/processing-jobs 均确认路由存在。
- php -l 针对 P1-11 新增和修改文件通过。
- php artisan test tests/Feature/PhotoProcessingTest.php 通过，5 个测试、17 个断言全部成功。
- php artisan test tests/Feature/PhotoUploadTest.php tests/Feature/PhotoManagementTest.php 通过，10 个测试、46 个断言全部成功。
- php artisan migrate --force 通过，已在本地数据库创建 photo_analysis_results 和 processing_jobs。
- php artisan db:seed --class=LocalGalleryDemoSeeder 通过，已生成 30 张图片、4 个相册、4 个首页专题、60 个图片处理任务、4 个模拟用户、15 条评论/纠错记录、124 条互动排行记录、4 个运营守护者赞助订单和 17 个用户勋章记录。
- 本地 Web 检查通过：首页 `/` 与 `/photos` 返回 200，`/admin/processing-jobs` 未登录返回 302。
- php artisan test 通过，172 个测试、1854 个断言全部成功。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php -l 针对 P1-10 后端新增和修改文件通过。
- php artisan test tests/Feature/BadgeTest.php 通过，5 个测试、26 个断言全部成功。
- php artisan test tests/Feature/UserCenterTest.php 通过，7 个测试、116 个断言全部成功。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan test 通过，167 个测试、1837 个断言全部成功。
- php artisan migrate --force 通过，已在本地数据库创建 badges 和 user_badges。
- php artisan db:seed --class=BadgeSeeder 通过。
- php artisan db:seed --class=LocalGalleryDemoSeeder 通过，已生成 30 张图片、4 个相册、4 个首页专题、4 个模拟用户、15 条评论/纠错记录、124 条互动排行记录、4 个运营守护者赞助订单和 17 个用户勋章记录。
- P1-9 收口验证保留：SponsorshipTest 通过，赞助相关迁移、Seeder、构建和全量测试已通过。

## 2026-09-04

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 完成 P1-7 用户中心与通知中心：新增 `/me`、`/me/favorites`、`/me/comments`、`/me/reports`、`/me/notifications` 五个登录用户页面，展示当前用户自己的资料、收藏、评论 / 纠错、举报和站内通知。
- 新增 `notifications` 表和 `UserCenterNotification`，评论审核、举报处理、账号封禁 / 解封会写入站内通知；前台通知只展示安全摘要。
- 将公开导航的个人中心入口从 `/dashboard` 调整到 `/me`，保留 `/dashboard` 登录边界但不作为前台主入口。
- P1-7 收口后补齐个人中心账号设置入口：`/me` 增加个人资料、修改密码、安全验证和外观设置四个入口，现有 settings 页面完成基础中文化，并修复误用 Laravel starter dashboard 外壳导致账号设置页出现 Dashboard 侧栏的问题。
- P1-7 收口后，当前唯一开发入口推进到互动统计与排行榜开发前确认稿；确认前不开发排行榜、赞助、勋章或后续社交扩展代码。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php -l 针对 P1-7 后端新增和修改文件通过。
- php artisan test --filter=UserCenterTest 通过，7 个测试、112 个断言全部成功。
- php artisan test --filter='UserCenterTest|PublicIdentityEntryTest|PublicPhotoInteractionTest|PublicPhotoCommentTest|PublicModerationTest' 通过，31 个测试、343 个断言全部成功。
- php artisan test 通过，150 个测试、1574 个断言全部成功。
- php artisan migrate --force 通过，已在本地数据库创建 notifications 表。
- php artisan test --filter='Settings|UserCenterTest' 通过，29 个测试、235 个断言全部成功。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过，前台资源、新增 Me 页面和账号设置页面可正常构建。
## 2026-09-02

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 整理 P1-7 用户中心与通知中心开发前确认稿：明确本切片只做 /me、/me/favorites、/me/comments、/me/reports、/me/notifications 五个登录用户页面和站内通知基础闭环，确认前不开发 P1-7 代码。
- 澄清 P1-7 本切片不做项不是永久取消，收藏夹、勋章、赞助、排行榜、动态流、外部通知、用户治理和后台互动管理等后续按独立切片确认；永久 / 默认禁做仍只沿用前台下载按钮和分享返利既有规则。
- 整理并完成 P1-6 评论审核、举报、封禁与敏感词基础过滤：本切片只做审核队列、举报处理、基础封禁和敏感词风险标记。
- 完成 P1-6 评论审核、举报、封禁与敏感词基础过滤：新增评论审核字段、reports 表、sensitive_words 表、后台评论审核 / 举报处理 / 敏感词 / 用户封禁入口，图片详情页支持已发布评论举报，被封禁用户仍可浏览但不能写入评论、纠错、举报、点赞或收藏。
- 完成 P1-5 图片评论与信息补充 / 纠错投稿：新增 `comments` 表、`Comment` 模型、评论/纠错提交接口和图片详情页评论区；公开评论只展示已发布普通评论，新提交内容默认待审核；`LocalGalleryDemoSeeder` 新增 4 个模拟用户和 15 条评论/纠错演示数据，并保持测试图片 UUID 重复 seed 时稳定。
- 整理 P1-5 图片评论与信息补充 / 纠错投稿开发前确认稿：明确本切片只做提交与展示基础，完整审核、举报、封禁和敏感词过滤放到 P1-6。
- 修复 P1-1 导航收口范围：新增 `PublicHeader` 公共导航组件，首页、图库列表、搜索页、相册列表、相册详情、专题列表、专题详情和图片详情统一复用同一套前台导航。
- 首页继续使用覆盖头图的透明导航和滚动浅色导航，其他公开页面使用浅色 sticky 导航；全局搜索、移动端抽屉和登录 / 注册 / 个人中心入口统一由公共组件负责。
- 同步进度口径：P1-2 账号基础与前台身份入口确认稿已由用户确认，当前唯一开发入口改为 P1-2 开发。
- 完成 P1-2 账号基础与前台身份入口：Inertia 全局共享 `canRegister`，公共导航统一根据登录状态显示登录 / 注册 / 个人中心，账号基础页面完成中文化，并新增公开身份入口回归测试。
- P1-2 收口后，当前唯一开发入口推进到 P1-3 站点说明与合规基础页开发前确认稿。
- 完成 P1-3 站点说明与合规基础页：新增 /about、/copyright、/takedown、/privacy、/terms 五个公开页面，复用公开导航和共享页脚；首页和静态页页脚兜底露出合规入口；下架页读取站点联系邮箱。
- P1-3 收口后，当前唯一开发入口推进到 P1-4 收藏 / 点赞 / 分享记录基础开发前确认稿。
- 完成 P1-4 收藏 / 点赞 / 分享记录基础：新增 photo_favorites、photo_likes、photo_shares 表、互动模型和图片互动接口；图片详情页新增收藏、点赞、分享按钮，点赞数公开展示，收藏数和分享数暂不公开。
- 明确 P1-4 后续规划口径：分享到微信/微博等第三方 API、分享海报生成、短链接、复杂社交动态流、后台互动管理、异常刷赞处理和排行榜管理后续独立切片再做；分享返利默认不做；前台下载按钮仍不作为默认能力。
- P1-4 收口后，当前唯一开发入口推进到 P1-5 图片评论与信息补充 / 纠错投稿开发前确认稿。
- 修复 P1-4 收藏 / 点赞交互体验：详情页按钮改为前端乐观更新，后端返回 JSON 小响应确认状态，避免 Inertia 整页刷新造成明显等待感。
- 继续修复 P1-4 收藏 / 点赞点击顿感：收藏和点赞按钮不再进入 native disabled 状态，不再显示等待光标或短暂加载态，防重复点击改由前端逻辑守卫处理。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan db:seed --class=LocalGalleryDemoSeeder 通过，已生成 30 张图片、4 个相册、4 个首页专题、4 个模拟用户和 15 条评论/纠错记录。
- php artisan test --filter=PublicPhotoCommentTest 通过，5 个测试、54 个断言全部成功。
- php artisan test --filter=PublicPhotoDetailTest 通过，5 个测试、146 个断言全部成功。
- php artisan test --filter=Public 通过，60 个测试、1136 个断言全部成功。
- php artisan test 通过，143 个测试、1462 个断言全部成功。
- npm run build 通过，前台资源可正常构建。
- Pint 针对本次 PHP 修改文件格式化通过。
- git diff --check 无空白错误，仅提示既有 CRLF/LF 行尾转换警告。
- php artisan test --filter=PublicModerationTest 通过，7 个测试、64 个断言全部成功。

## 2026-09-01

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 完成 P1-1 首页导航 UI/UX 对齐补漏：首页桌面端调整为左侧公开入口、中间 LOGO / 站点名、右侧搜索栏和账号入口，移动端抽屉补齐搜索、公开入口和账号入口。
- 首页最新照片和专题模块支持启用但无数据时显示空状态，并将当前唯一开发入口推进到 P1-2 账号基础与前台身份入口开发前确认稿。

- 修复公开媒体 URL 生成规则：本地 `public` 磁盘返回 `/storage/...` 同源相对路径，解决 `APP_URL=http://localhost` 缺少端口导致首页、图库、相册、专题和详情页图片无法显示的问题。

- 完成 P0 总体验收巡检文档收口，整理出 P0 公开列表 / 搜索边界补漏确认稿，并按确认后开发的节奏处理。
- 完成 /photos 和 /search 的公开边界补漏：公开关键词只匹配标题和说明，列表/搜索卡片不输出 source 对象，相册筛选项只列出已发布且至少包含 1 张公开图片的相册。
- 更新 PublicPhotoGalleryTest 和 PublicSearchTest，补充文件名不参与公开搜索、列表/搜索 payload 不暴露后台字段、相册筛选项过滤空/非公开相册的断言。
- P0 核心 MVP 已完成收口，当前唯一开发入口推进到下一阶段开发顺序确认稿；确认前不改业务代码。
- P1 第一候选切片补充为首页导航 UI/UX 对齐补漏：左侧页面入口、中间 LOGO / 站点名、LOGO 右侧搜索栏、最右侧登录 / 注册 / 个人中心入口，并纳入首页最新照片 / 专题无数据空状态占位判断。
- 新增本地测试图片目录 `.scratch/messi-gallery/test-images/`，用于后续相册、专题、最新照片、图片详情和搜索验收；真实图片文件通过 `.gitignore` 排除。
- 新增 `LocalGalleryDemoSeeder`，可根据本地测试图片生成前台可浏览的测试图片、相册、首页头图和专题配置。
- 整理 PRD P1 第二阶段开发顺序确认稿，明确 P1 按切片串行确认和开发，不一次性全部开工。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan test 通过，118 个测试、1132 个断言全部成功。
- npm run build 通过，前台资源可正常构建。
- php artisan test --filter=PublicPhotoGalleryTest 通过，6 个测试、133 个断言全部成功。
- php artisan test --filter=PublicSearchTest 通过，6 个测试、127 个断言全部成功。
- php artisan route:list 已确认 P0 前后台关键入口存在。
- git diff --check 无空白错误，仅提示既有 CRLF/LF 行尾转换警告。
- php artisan test --filter=PublicModerationTest 通过，7 个测试、64 个断言全部成功。

## 2026-08-31

### Added

- 完成 `系统设置 / 首页配置` 基础切片：新增 `settings` 表、`Setting` 模型、`HomepageSettings` 服务和 Filament `系统设置` 页面。
- 后台首页配置支持站点基础、导航、头图轮播、分类模块、最新照片、专题模块、页脚和内部备注的结构化维护。
- 新增 HomepageSettingsManagementTest，覆盖 settings 表、JSON 配置保存、默认配置、公开内容校验和后台设置页访问。
- 完成前台首页基础切片：新增 `PublicHomepage` 数据组装服务和 `HomeController`，`/` 路由改为输出首页配置、公开图片、分类展示和专题配置。
- 完成前台图库列表 `/photos` 基础切片：新增 `PublicPhotoGallery` 数据组装服务、`PhotoGalleryController`、`Photos/Index.vue` 和 PublicPhotoGalleryTest。
- 完成前台搜索页 `/search` 基础切片：新增 `SearchController`、`Search/Index.vue` 和 PublicSearchTest，复用公开图片查询能力。
- 完成前台相册列表 `/albums` 基础切片：新增 `PublicAlbumIndex` 数据组装服务、`AlbumIndexController`、`Albums/Index.vue` 和 PublicAlbumIndexTest。
- 完成前台相册详情 `/albums/{slug}` 基础切片：新增 `PublicAlbumDetail` 数据组装服务、`AlbumDetailController`、`Albums/Show.vue` 和 PublicAlbumDetailTest。
- 完成前台专题列表 `/topics` 基础切片：新增 `PublicTopicIndex` 数据组装服务、`TopicIndexController`、`Topics/Index.vue` 和 PublicTopicIndexTest。
- 完成前台专题详情 `/topics/{slug}` 基础切片：新增 `PublicTopicDetail` 数据组装服务、`TopicDetailController`、`Topics/Show.vue` 和 PublicTopicDetailTest。
- 完成前台图片详情 `/photos/{uuid}` 基础切片：新增 `PublicPhotoDetail` 数据组装服务、`PhotoDetailController`、`Photos/Show.vue` 和 PublicPhotoDetailTest。

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 将 Laravel Starter 默认欢迎页替换为梅西影像档案库首页，实现透明/滚动导航、移动端抽屉、头图、分类切换、最新照片三列画廊、专题图片带和页脚。
- 整理并确认 `系统设置 / 首页配置` 开发前确认稿，覆盖站点基础、导航、头图轮播、分类模块、最新照片、专题模块、页脚、权限、交互、兜底和验收标准。
- 整理前台首页开发前确认稿，覆盖页面范围、数据来源、展示内容、按钮入口、排序、状态边界、交互效果、UI/UX、响应式、权限、性能和验收标准。
- 统一进度口径：`docs/project/development-progress.md` 为唯一进度事实源，`.scratch/messi-gallery/` 任务文件只做状态镜像。
- 固定后续开发节奏：确认稿 -> 用户确认 -> 开发 -> 测试/构建 -> 文档收口 -> 下一切片确认稿。
- 明确后续沟通不能只依赖编号，必须同时写清功能名和路由；当时唯一开发入口为前台图库列表 `/photos` 开发前确认稿。
- 整理前台图库列表 `/photos` 开发前确认稿，覆盖页面范围、数据来源、展示内容、按钮入口、筛选、排序、状态边界、交互、UI/UX、响应式、权限、性能和验收标准。
- 图库列表支持公开图片过滤、关键词/分类/标签/相册/日期筛选、排序、分页、筛选回显、移动端筛选面板和稳定空状态。
- 前台图库列表 `/photos` 已完成收口，并按流程进入过前台搜索页 `/search` 开发前确认稿。
- 整理前台搜索页 `/search` 开发前确认稿，覆盖页面范围、数据来源、展示内容、按钮入口、筛选、排序、状态边界、交互、UI/UX、响应式、权限、性能和验收标准。
- 搜索页支持公开图片关键词搜索、分类/标签/相册/日期组合筛选、排序、分页、关键词清空、全部清空和稳定空状态；搜索页收口后按流程进入过前台相册列表 `/albums` 开发前确认稿。
- 整理前台相册列表 `/albums` 开发前确认稿，覆盖页面范围、数据来源、展示内容、按钮入口、筛选、排序、状态边界、交互、UI/UX、响应式、权限、性能和验收标准。
- 相册列表支持公开相册过滤、至少 1 张公开图片约束、手动封面优先与公开图片兜底、关键词/分类筛选、排序、分页和稳定空状态；当前唯一开发入口推进到前台相册详情 `/albums/{slug}` 开发前确认稿。
- 整理前台相册详情 `/albums/{slug}` 开发前确认稿，覆盖页面范围、数据来源、展示内容、按钮入口、相册内图片排序、状态边界、交互、UI/UX、响应式、权限、性能和验收标准。
- 相册详情支持公开相册访问、公开图片过滤、手动公开封面优先与公开图片兜底、相册内排序、分页和稳定 404 边界；当前唯一开发入口推进到前台专题列表 `/topics` 开发前确认稿。
- 补充协作规则：后续文档收口后的最终回复必须列出同步过的文档具体位置。
- 整理前台专题列表 `/topics` 开发前确认稿，覆盖页面范围、数据来源、展示内容、按钮入口、筛选、排序、状态边界、交互、UI/UX、响应式、权限、性能和验收标准；确认前不开发专题列表代码。
- 专题列表支持读取首页专题配置，展示已启用且有效的站内专题入口，过滤禁用项、无标题项和异常链接；当前唯一开发入口推进到前台专题详情 `/topics/{slug}` 开发前确认稿。
- 整理前台专题详情 `/topics/{slug}` 开发前确认稿，明确 P0 不新增 `topics` 表，先轻量扩展现有首页专题配置项，覆盖主题说明、关联相册、精选图片、公开过滤、交互、UI/UX、响应式和验收标准；该确认稿已由用户确认并完成基础实现。
- 专题详情支持读取 `home.topic_module.items` 中 URL 匹配 `/topics/{slug}` 的已启用专题项，展示专题说明、关联公开相册和精选公开图片，过滤不可公开内容；当前唯一开发入口推进到前台图片详情 `/photos/{uuid}` 开发前确认稿。
- 整理前台图片详情 `/photos/{uuid}` 开发前确认稿，覆盖页面范围、数据来源、展示内容、按钮入口、上下文、排序、状态边界、交互、UI/UX、响应式、权限、性能和验收标准；确认前不开发图片详情代码。
- 明确图片详情后续阶段边界：评论 P0 不做、P1 再做；收藏、点赞、灯箱、纠错投稿、图片信息补充和相似图推荐放到后续阶段；前台下载按钮永远不做默认能力。
- 图片详情支持公开图片访问、非公开图片 404、来源公开字段脱敏、相册/专题上下文、上一张/下一张、分类/标签回链和轻量相关推荐；当前唯一开发入口推进到 P0 总体验收/补漏确认稿。
- 整理 P0 总体验收/补漏确认稿，明确下一步只做 P0 已完成切片的入口、链路、公开边界、测试和文档一致性巡检；确认前不写新功能代码。
- 在数据库设计文档补充 `settings` 逻辑模型，并修正来源索引摘要与 P0 来源字段一致。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan test 通过，118 个测试、1132 个断言全部成功。
- npm run build 通过，前台资源可正常构建。
- php artisan test --filter=PublicPhotoGalleryTest 通过，6 个测试、133 个断言全部成功。
- php artisan test --filter=PublicSearchTest 通过，6 个测试、127 个断言全部成功。
- php artisan test --filter=PublicSearchTest 通过，6 个测试、120 个断言全部成功。
- php artisan test --filter=PublicAlbumIndexTest 通过，5 个测试、120 个断言全部成功。
- php artisan test --filter=PublicAlbumDetailTest 通过，5 个测试、117 个断言全部成功。
- php artisan test --filter=PublicTopicIndexTest 通过，3 个测试、53 个断言全部成功。
- php artisan test --filter=PublicTopicDetailTest 通过，5 个测试、85 个断言全部成功。
- php artisan test --filter=PublicPhotoDetailTest 通过，5 个测试、146 个断言全部成功。

## 2026-08-30

### Added

- 完成图片批量上传基础切片：新增 photo_upload_batches 表、photos.photo_upload_batch_id 字段、PhotoUploadBatch 模型和 PhotoUploadService。
- 在 Filament 图片管理页新增“批量上传”动作，支持一次上传多张图片、关联目标相册、记录来源和版权状态，并生成上传批次。
- 新增 PhotoUploadTest，覆盖上传批次表、系统自动重命名、草稿状态、重复上传生成独立图片记录、相册上传继承相册分类和图片批次关系。

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 图片上传默认标题改为系统重命名后的文件名；原始文件名仅作为追溯字段保留。
- 上传流程文档同步为 Phase 1 真实规则：上传后进入 draft 草稿/待整理，不做重复检测、哈希拦截、感知哈希或相似图检测。
- 补充 `上传批次 / 批量整理` 开发前确认稿，覆盖批次列表、整理详情、批量操作、发布校验、权限、交互、UI/UX、空状态和验收标准。
- 完成上传批次 / 批量整理基础切片后，将下一步推进到系统设置 / 首页配置开发前确认。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan test 通过，64 个测试、228 个断言全部成功。

## 2026-08-29

### Added

- 完成来源管理基础切片：新增 sources 表、Source 模型、Filament 来源管理资源和 SourceManagementTest。
- 完成图片核心模型基础切片：新增 photos、photo_category、photo_tag 表，Photo 模型，模型关系，Filament 图片管理资源和 PhotoManagementTest。

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 确认 P0 页面范围整体通过；专题列表和专题详情进入 P0；分类/标签/相册已开发基础切片保留。
- 确认下一批开发顺序为来源管理、图片核心模型、图片批量上传、上传批次/批量整理、首页配置、前台 P0 页面。
- 确认来源管理 P0 字段收窄为原始链接、原始发布日期、版权备注和内部备注；内部备注只给后台看。
- 确认后续每个切片开发前必须一次性讨论完字段、按钮、筛选、排序、状态、权限、验收标准、交互方式和 UI/UX 风格，不允许一轮只问一个细碎问题。
- 确认图片详情页前台永远不做默认下载按钮；未登录用户允许浏览全部公开内容，包括已发布图片、已发布相册和公开专题。
- 确认时间线归档保留为 P1；P0 首页导航只保留完整顺序概念，未实现时前台不展示时间线入口。

## 2026-08-25

### Added

- 新增 docs/project/development-progress.md，记录项目阶段、MVP 范围、开发进度、下一步计划和待确认问题。
- 新增本地任务 .scratch/messi-gallery/001-taxonomy-admin.md。
- 新增 categories、tags 迁移表。
- 新增 Category、Tag 模型。
- 新增 GalleryTaxonomySeeder，预置 7 个固定主分类和各自的 待补充子分类。
- 新增 Filament 子分类管理和标签管理资源。
- 新增 GalleryTaxonomyTest，覆盖分类 Seeder、标签类型和后台资源访问。
- 新增 albums、album_category、album_photo 迁移表。
- 新增 Album 模型和 Filament 相册管理资源。
- 新增 AlbumManagementTest，覆盖相册表结构、默认状态、7 类完整性、album_photo 多相册关系和后台资源访问。

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 新增文档维护硬规则：以后禁止新增新的说明类文档文件，只允许修改现有主文档。
- 将开发进度调整为暂停新增功能开发，先完成前台/后台页面清单、每页功能和 UI/UX 边界确认。
- 将 docs/project/development-progress.md 升级为项目路线图与开发进度的唯一主入口，补充全项目阶段路线图。
- 将 Laravel 默认语言、回退语言和 Faker 语言设置为 zh_CN，Filament 后台默认显示简体中文。
- 确认第一版 MVP 只做图库核心闭环，评论、VIP、微信小程序、OCR、相似图去重和以图搜图暂不进入第一版。
- 确认 Phase 1 只落图库核心表，社交、赞助、支付、AI 分析和系统任务表先保留在数据库设计文档中。
- 确认 Phase 1 分类模型采用 7 个必选主分类 + 子分类 + 多标签。
- 修正 Phase 1 文档范围：teams、competitions、matches 不进入当前第一阶段落库范围，album_photo 保留为图库核心表。
- 确认 7 个必选主分类为：生涯阶段、赛事、赛季、年份、场景、图片类型、来源平台；每个主分类预置待补充子分类。
- 确认 Phase 1 主分类固定，不允许后台随意新增第 8 个主分类；子分类允许后台自由新增、编辑和排序。
- 确认 Phase 1 加入相册/专题功能；相册必须选择 7 个主分类下的子分类，图片加入相册后分类完全同步相册，相册内图片不额外补充分类。
- 确认 Phase 1 一条图片记录可以加入一个或多个相册，通过 album_photo 维护关系；同一视觉图片重复上传到不同相册时，视为自动重命名后的不同图片记录。
- 确认 Phase 1 相册封面可以手动选择；手动封面只能选择相册内已发布图片；未手动选择时，系统默认从相册内已发布图片中随机选择一张作为封面展示。
- 确认 Phase 1 相册需要发布状态：draft 草稿、published 已发布、hidden 隐藏；新建相册默认草稿，只有已发布相册在前台展示。
- 确认 Phase 1 相册发布前必须至少包含 1 张已发布图片；否则不允许发布，并提示管理员或编辑先上传并发布图片。
- 确认 Phase 1 已发布相册的前台页面只展示已发布图片，不展示草稿/待整理图片。
- 确认 Phase 1 已发布相册必须始终至少保留 1 张已发布图片；如果图片状态变更、删除或移出相册会导致相册没有已发布图片，后台必须阻止操作，并提示先将相册改为草稿或隐藏。
- 确认相册和单独上传图片都必须选满 7 个主分类；信息不知道时选择对应主分类下的待补充，不能留空。
- 确认 Phase 1 标签允许后台自由新增、编辑和排序，但每个标签必须归属一个标签类型。
- 确认 Phase 1 预置 8 个标签类型：动作、情绪、画质、人物关系、荣誉、画面内容、服装/装备、地点。
- 确认 Phase 1 上传图片时不做重复检测、哈希拦截、感知哈希检测或相似图检测；同一视觉图片重复上传时视为多条独立图片记录。
- 确认相似图去重后置为后台管理能力，不阻塞第一版上传流程。
- 确认 Phase 1 支持批量上传到相册，并在上传后进入批量整理页面，逐图补充标题、说明、标签和发布状态。
- 确认 Phase 1 图片上传后默认进入草稿/待整理状态，点击发布后立即发布并对前台可见。
- 确认 Phase 1 单独上传且不属于任何相册的图片，只要状态为已发布且 7 个主分类已选满，也应在前台图片列表、分类检索和首页最新照片模块中展示。
- 确认 Phase 1 图片上传后自动重命名，格式为 YYYYMMDD-HHmmss-6位随机码.原扩展名；标题默认使用重命名后的文件名，发布前可改可不改；标题不允许为空；数据库保留原始文件名。
- 确认 Phase 1 图片说明可以为空，不阻塞整理和发布。
- 确认 Phase 1 图片标签可以为空，不阻塞整理和发布。
- 将 DatabaseSeeder 改为可重复执行的幂等写法。
- 为 User 模型补充 Filament 后台访问接口；local/testing 环境允许访问 admin 面板，生产环境待角色字段落地后再放开。
- 相册后台管理切片完成；后续根据用户反馈暂停继续新增功能开发，先确认完整页面功能确认稿。
- 合并冗余开发前文档：UI 内容统一到 docs/ui/page-map.md，Phase 1 规格统一到 docs/project/development-progress.md，项目管理规则统一到 docs/development/development-standards.md，架构决策统一到 docs/architecture/technical-architecture.md。
- 将上传批次明确为 Phase 1 后台 P0 数据依赖，并补充来源管理字段，支撑批量整理和版权追溯。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- php artisan test 通过，49 个测试、174 个断言全部成功。
- php artisan migrate --seed 已在本地 SQLite 执行成功。

## 2026-08-24

### Added

- 新增项目开发前文档包。
- 新增 AGENTS.md，定义 AI agent 工作原则。
- 新增 CONTEXT.md，定义项目领域语言。
- 新增 PRD、技术架构、数据库设计、UI 页面映射、第三方服务、部署运维、项目管理和开发规范文档。
- 新增 ADR-0001，记录采用 Laravel + Filament 的架构决策。
- 新增 .scratch/messi-gallery/README.md，作为本地任务追踪入口。
- 初始化 Laravel 12 项目骨架，采用 Inertia.js + Vue 3 + TypeScript + Tailwind CSS。
- 安装并生成 Filament 5 后台面板，后台入口默认为 /admin。
- 生成本地 .env 的 APP_KEY，并完成前端依赖安装与基础构建验证。

### Changed
- 完成后台批量上传发布体验增强：支持选择草稿或处理完成后自动发布，未选相册时支持批量选择 7 个主分类，并可分别开启 OCR / 智能标签自动队列任务。基础处理完成且满足既有发布条件后自动发布，否则保留草稿。
- 新增已归档图片“恢复为草稿”操作；归档和发布都会清除自动发布意图。相似候选继续保持逐图手动触发，不改为全库自动扫描。
- 新增 `photos.publish_after_processing` 迁移；定向测试 30 个、126 个断言通过，全量测试 227 个、2793 个断言通过。
- 修复后台图片管理页面在渲染“查看相似候选”动作时缺少资源类导入导致的 500，并补充真实图片记录下的后台页面回归测试。
- 完成 OCR 后台手动处理切片：新增 ocr 队列任务，调用数据万象 opticalOcrRecognition 读取 COS 原图并回写 photo_analysis_results.ocr_text；后台图片管理支持手动触发、查看、重试和清空。
- OCR 不进入上传同步链路，不开放前台 OCR 搜索、自动标题、自动标签或外部搜索引擎；真实云连通性仍需配置用户自己的 COS 凭证后单独验证。下一步进入图片质量评分开发前确认稿。
- 完成相似图首个后台辅助切片：新增 64 位 dHash 感知特征、相似候选关系、后台人工结论和 similarity 队列任务；不自动删除、合并、下架或改变发布状态。
- 后台图片管理可手动触发指定图片的相似候选计算；新增相似候选资源，支持查看双方状态、相似度、精确 SHA-256 提示并标记为保留独立、确认重复候选或忽略。
- 相似图专项测试覆盖候选生成、人工结论、失败记录和重试；未接入前台相似推荐、以图搜图或外部搜索引擎。相似图和 OCR 后台辅助切片均已完成，下一步进入图片质量评分开发前确认稿。
- 完成腾讯云数据万象首个真实处理切片：在 COS 模式且后台开关开启时，后台队列调用 qcloud/cos-sdk-v5 生成展示图和缩略图，并回写现有图片 Key。
- 新增 datawanxiang_derivatives 任务类型，支持确定性派生图 Key、错误记录、120 秒超时、最多 3 次队列尝试、退避和人工重试；本地存储模式不调用云服务。
- 新增数据万象适配层测试；真实 COS 临时读写已通过，数据万象真实请求待当前 COS 存储桶存在业务原图后验证。

- 技术架构从 Blade/Livewire 起步调整为 Laravel + Inertia.js + Vue 3 + TypeScript + Tailwind CSS + Filament。
- 明确后台第一版即使用 Filament，不使用 Naive UI / Arco Design 自建后台。
- 通过 npm audit fix 更新前端锁文件，将 npm 审计告警降为 0。

- 完成项目后台本地/COS 两种存储切换：在现有 Filament 系统设置中增加存储方式开关、腾讯云凭证、地域、存储桶、可选 CDN 和数据万象开关；本地指项目当前运行环境，不再拆分电脑本地与 VPS 本地。
- 新增 StorageSettings、PhotoStorage 和 ProcessPhotoAnalysisJob；腾讯云 SecretKey 加密保存，图片上传使用当前启用存储，历史本地图片可继续读取，图片 metadata/hash 任务通过队列执行。
- 新增当前存储读写检测和 StorageSettingsTest，未配置腾讯云时本地上传、公共展示、站内搜索和后台任务行为保持不变；COS 适配器依赖已补齐，数据万象 OCR、标签和相似图真实 API 仍按验证范围使用。
### Verified

- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npx.cmd vue-tsc --noEmit 通过。
- npm run build 通过。
- npm run build 通过。
- php artisan test 通过，41 个测试、132 个断言全部成功。


## 2026-11-02 首页视觉整改

### Changed
- 首页首屏、相册、照片与专题采用统一的蓝白排版及响应式组件；保持全部模块、数量、顺序、配置字段和目标链接。
- 新增GSAP首屏/分类轻动效及减少动态效果适配，保留原轮播和分享搜索规则。

### Verified
- 首页与导航组件12项、相关PHP测试12项/105断言通过；类型、相关ESLint、生产构建及本地浏览器功能回归通过。未部署线上。


### 首页视觉反馈修订（2026-11-02）

按用户反馈覆盖上一轮分栏与完整图展示方案：首屏恢复全宽铺满的背景幻灯片，文字和原按钮叠放于图片上，沿用所有头图配置及切换规则；最新照片取消可见名称，保留aria-label和alt，使用object-fit: cover铺满4:3区域，消除横竖图留白。主题基色改为天蓝#75AADB，搭配白色和浅蓝表面，专题区与选中标签均采用天蓝，不再使用深蓝大底。图片会按容器裁切，不修改图片文件。12项组件测试、类型检查、相关ESLint、生产构建通过；浏览器验证三张轮播、五个分类、15张照片、五个专题、搜索分享及320～1440px响应式均通过，三尺寸截图无破图/控制台错误/横向溢出。仅本地修改，未部署。


### AFA 官网配色同步（2026-11-04，本地）

根据用户指定的 https://www.afa.com.ar/es/ 实际浏览器计算样式，首页采用浅天蓝#98CEF0、文字#042240、白色#FFFFFF、辅助表面#F5F5FA及少量金色#BA9F6C。PublicHeader新增可选afa主题，仅首页启用，天蓝导航始终选用浅底适配标识；其他页面默认主题、菜单、搜索及账号逻辑不变。保留全宽头图与铺满的无名称照片网格。13项组件回归、类型检查、相关ESLint及生产构建通过，浏览器确认导航颜色与官网一致、手机菜单开合正常。未部署线上。


### AFA配色范围纠正（2026-11-04）

移除AFA主题强制固定深色LOGO的条件及LOGO白色底板，恢复首页顶部透明导航/浅色标识、滚动后实底/深色标识、返回顶部恢复，以及移动菜单开关切换。AFA主题只覆盖实底状态的天蓝背景与文字颜色，不改变原有导航状态逻辑。8项导航行为测试和生产构建通过。


### 专题居中轮播（2026-11-04，本地）

用户确认参考AFA荣誉轮播：专题隐藏横向滚动条，使用左右箭头将相邻专题移到中心，首尾可循环选择；默认有至少两项时选中第二项，只有一项时不显示箭头。不克隆条目，保持配置数量与原链接。居中项遮罩透明度0.54，非选中项悬停/键盘聚焦为0.30，普通项0.14；中心态不因移开鼠标消失。保留原生横向触摸滚动，滚动停止同步最近中心项，容器缩放重新定位，减少动态效果时即时切换，卸载清理观察器与计时器。13项组件测试、类型/相关ESLint/构建通过；浏览器验证箭头、循环、遮罩层级及320/390/768/1440px居中均通过，无整页横向溢出。未部署线上。


### 专题素材细白边修正（2026-11-04）

浏览器确认专题封面已使用cover且无内边距，残留白边来自原图片内嵌边框。仅专题展示层使用104%尺寸、四边超出2%后由卡片裁切，隐藏细白边，不修改原图文件、专题链接、轮播或遮罩。生产构建与实际浏览器截图检查通过，减少动态效果模式下也维持该静态裁切。

### 首页模块间距、页脚与分享弹窗（2026-11-04，本地）

精选相册“更多”移至分类导航同行，仍使用后台原链接。模块上下内边距由桌面80px缩至48px、移动48px缩至32px；相册底部与最新照片顶部进一步设为桌面28px、移动24px。首页页脚使用浅灰底、天蓝分隔线，站点信息与链接分区；手机端堆叠并预留悬浮分享按钮空间。首页分享弹窗改为天蓝标题区、独立复制链接入口及双列渠道卡片，提供说明文字和键盘焦点反馈；保留原分享、二维码、海报与统计逻辑，其他页面不启用首页专用主题。

验证：类型、相关ESLint、生产构建通过；13项前端组件测试、20项分享推广测试（206个断言）通过。实际浏览器检查320/390/768/1440px无整页和弹窗横向溢出，五个分享渠道可见、微信二维码可展开，页脚链接与后台配置逐项一致。未部署线上。

### 首页页脚结构重设计（2026-11-04，本地）

按用户反馈重做页脚结构：白底介绍/双列帮助链接、天蓝大字号站名及原LOGO、独立版权备案信息；手机纵向重排。参考Footer Design排版案例，沿用AFA配色；GSAP ScrollTrigger提供一次性轻入场并支持减少动态效果及路由清理。仅首页启用新主题，保留所有配置与功能。类型/相关ESLint/构建/13项前端测试及浏览器320/390/768/1440px链接、无溢出、44px触摸区、版权页往返、键盘与减少动态效果检查通过。详见页面映射主文档，未部署。

### 首页页脚比例纠正（2026-11-04，覆盖同日大字品牌方案）

用户明确否定巨型站名、口号和大面积天蓝收尾。本轮删除上述处理，页脚恢复辅助信息的正常层级：浅灰底，左侧原LOGO/23px站名及简短项目说明，右侧按原链接目标分为站点信息、版权与使用；其他后台链接仍进入站点信息，存在社媒时独立成组。底部单列原版权与备案，手机版纵向排列且保留悬浮工具避让。桌面实际高度约313px（前版504px），站名不再随屏宽放大；轻入场降为8px、400ms。默认非首页模板不变。

验证：类型、相关ESLint、生产构建和13项已有前端行为测试通过；实际浏览器检查桌面及2560宽屏比例。原站名、LOGO、版权、备案和全部链接来源仍为后台配置，不修改业务功能。此前大字方案不再作为当前生效设计。

### AFA完整配色与金色角色补齐（2026-11-04，本地）

用户要求整个首页使用AFA配色体系，金色不能仅停留在变量定义。重新实际浏览https://www.afa.com.ar/es/，计算样式确认天蓝#98CEF0用于导航，金色#BA9F6C用于冠军横条、Efemérides标题和页脚栏目，LOGO也体现品牌金色。本轮保持原页面结构，将金色用于首页主按钮、轮播选中点、相册分类选中态、链接箭头、专题选中底部标记及进入箭头、分享复制入口图标和部分悬停反馈。专题选中遮罩、自动轮播、导航LOGO切换等原功能不变。

页脚改为白底，原PNG轮廓通过CSS遮罩显示精确品牌金色，不修改原素材或后台配置；站名和正文仍用#042240，次要文字仍为灰蓝。白色与天蓝保持主背景，#F5F5FA仅作为小面积辅助表面。#BA9F6C对白色对比度约2.54:1，不用于小字号正文；深蓝字对金底约6.32:1。金色作为品牌与状态强调，不按官网原样复制版式。

验证：类型、相关ESLint、构建、13项前端测试通过。实际浏览器确认主按钮/分类/页脚LOGO/专题选中标记/分享入口均渲染精确金色，320/390/768/1440px无整页横向溢出，原图片与专题数量不变；查看桌面头图、专题页脚及手机截图。未部署线上。

### 页脚社交媒体入口（2026-11-04，本地）

按用户提供的二维码与地址，在公共页脚站点信息下增加微信、微博、B站、微信公众号四个图标按钮。两张二维码原文件复制到resources/images/social，哈希核对一致，不重绘、不修改内容；图标采用Simple Icons的标准微信/微博/B站SVG，以深蓝显示、金色悬停反馈。微信悬停/键盘聚焦显示添加好友二维码，附文“添加好友请注明来意”；公众号显示另一张原始二维码。移动端点击展开，点击外部、移出或Escape关闭，一次只显示一张，244px弹层支持320px窄屏且鼠标可移入二维码。外链使用用户指定微博https://weibo.com/u/5250260756与B站https://space.bilibili.com/25231526，新窗口打开并附noopener noreferrer。原后台配置社媒和其他链接保持不变。

新增FooterSocialLinks组件供首页及原默认页脚复用，卸载清理外部点击监听。类型、相关ESLint、生产构建及13项原前端测试通过；桌面实际浏览器已验证悬停、移入二维码、聚焦/Escape及外链地址。

补充验收：320/390/768px两张二维码均在视口内、图片加载正常、点击外部可关闭；浏览器触摸模拟实际轻点微信图标展开成功。


## 第一批前台视觉整改实施完成（2026-11-04，本地）

用户确认设计与导航/LOGO/分页反馈后授权实施。完成/photos和/search的紧凑布局、完整筛选浮层/手机面板、原色吸顶导航、共用照片卡片/分页、AFA配色和轻GSAP动效；保持原字段、URL、结果、详情链接、条件回显和分页业务。archive页脚仅扩展到两页，修正结果高度变化后的入场定位，不影响其他页面默认样式。详情见页面主文档H节。

类型/相关ESLint/Prettier/构建通过；25项后端测试525断言、13项首页导航前端测试通过；最终浏览器13组交互/四档响应式/首页回归检查通过，无脚本错误。真实截图在.scratch/artifacts/*-live-*.png；未部署。下一批仍需先设计确认，暂不改图片详情、相册或专题页面。

## 第二批2A图片详情设计交付（2026-11-04，待确认）

- 按首页视觉基准及Taste、finesse-ui、GSAP规范制作W03图片详情独立设计稿，真实浏览器审查竖图、横图、相册上下文及移动布局；完整主图、紧凑资料、图下操作和推荐4/3/2列，保留现有互动/评论/纠错/举报及上下文规则。
- 同步docs/ui/page-map.md的I节与W03状态、docs/project/development-progress.md；第一批W02/W04仍为已完成，W03仅设计待确认。原型与截图位于.scratch/artifacts/photo-detail-design，未修改正式详情页，未部署。
- 浏览器四档尺寸、主图/原色LOGO/吸顶、分享、表单和上下文检查完成；Taste自动检查器受本机Chrome启动故障阻断，由Tabbit实际页面检查替代，不计为自动审计通过或业务写入验收。

### 第二批2A设计修订：手机图片资料折叠（2026-11-04，未实施）

- 图片详情独立设计稿增加手机默认关闭的“图片资料”折叠区，保留标题、操作和全部资料链接；桌面/平板资料展开。支持点击、Enter/Space、明确展开状态及减少动态效果。
- 浏览器检查320/390/768/1440px和断点切换通过；更新手机截图、页面主文档I5与主进度。未修改正式详情页。

## 第二批2A图片详情正式整改完成（2026-11-04，本地）

- 按确认稿整改Photos/Show.vue与局部Show.css：完整主图、紧凑资料栏、原色吸顶导航、图下互动、手机默认折叠资料、4/3/2列推荐和减少动态效果；保留原业务处理、元数据及相册/专题上下文。
- SharePanel增加可选archive外观，本页复用首页分享样式；公共archive页脚扩展到单段图片详情路径，原渠道/推广/二维码与其他页面默认外观不变。
- 类型/相关ESLint/格式/生产构建通过；42项后端测试517断言、13项前端测试及实际浏览器四档布局和主链路回归通过。同步页面主文档J节、W03状态和主进度；未部署线上。

## 第二批2B相册设计交付（2026-11-04，待确认未实施）

- 制作W07相册列表与W08相册详情独立设计稿，沿用首页AFA外壳、3/2/1列相册卡片、8分类筛选、手机资料折叠与4/3/2列照片；原排序/数量/分享/相册上下文保护列入页面主文档K节。
- 实际浏览器审查原页并验证原型四档响应式、筛选/焦点/折叠和异常状态；同步主进度、W07/W08待确认状态及下一批顺序。未修改正式相册页面，未构建或部署。

## 第二批2B相册正式整改完成（2026-11-04，本地）

- 按确认稿实施Albums/Index、Albums/Show及局部Albums.css，复用图库控件/照片/分页，保留原查询、排序语义、全部资料和相册图片上下文；手机相册资料默认折叠。
- 两页复用首页AFA导航、archive分享和页脚；其他页面默认外观不变。
- 类型/相关ESLint/格式/生产构建、13项前端测试及后端38项675断言通过；新增分页条件/相册上下文行为测试。真实浏览器四档尺寸和两个相册主链路通过；同步页面主文档L节、W07/W08完成状态和主进度。未部署。

## 第二批2C专题设计交付（2026-11-04，待确认未实施）

- 制作专题列表和详情独立设计稿：AFA基准、3/2/1列遮罩封面、紧凑介绍/手机折叠、关联相册与精选图原顺序及topic链接保留；单相册采用桌面横排。
- 实际浏览器验证四档尺寸、两组真实专题数据、焦点/遮罩/折叠/空态等状态通过；同步页面主文档M节、W09/W10待确认状态及主进度。未修改正式页面、未构建或部署。

## 第二批2C专题正式整改完成（2026-11-04，本地）

- 按确认稿实施Topics/Index、Topics/Show和局部Topics.css，复用图库/相册/分享/页脚；原专题入口、配置顺序、内容条件、资料及topic图片上下文保留，未新增筛选或分页。
- 四档响应式、手机介绍折叠、单相册横排、遮罩和可见焦点实现。类型/相关ESLint/格式/生产构建、后端46项813断言、13项前端测试及实际浏览器主链路回归通过。
- 同步页面主文档N节、W09/W10完成状态和主进度；异常内容使用浏览器响应模拟，未改真实数据，未部署。下一批W05/W06以图搜图仍先设计确认。

## 第三批3A以图搜图设计交付（2026-11-04，待确认未实施）

- 制作W05/W06共用模板的初始/选图/提交/等待/结果/失败等独立设计稿；保持原轮询、会话、临时文件及404边界，采用AFA外壳、完整本地预览和4/3/2列结果卡片。
- 实际浏览器检查原页、本地选图及原型四档响应式与状态；结果只为公开图片排版示例，无POST或真实查询。同步页面主文档O节、W05/W06待确认状态及主进度；未改正式页面、未构建/部署。

## 第三批3A以图搜图正式整改完成（2026-11-04，本地）

- 按确认稿实施Search/Image共用模板与局部Image.css，保留原上传/轮询/清理/会话/404规则；统一AFA导航页脚、预览/错误/等待与4/3/2列结果。
- 类型/相关ESLint/格式/构建、24项后端502断言和13项前端测试通过；浏览器完成真实提交与1条匹配结果、文件大小/预览/返回及四档回归，另以虚拟时间验证60次轮询停止与手动刷新。
- 真实查询在观察期仍排队，仅CLI处理本轮一条临时任务并确认文件清理，不表示工作器自动消费已验收；其余异常状态为浏览器模拟。同步页面主文档P节、W05/W06完成状态和主进度，未部署。

## 第三批3B球队与赛季设计交付（2026-11-04，待确认）

- 制作W11–W14四页独立设计稿，延续AFA外壳、紧凑页头、原入口与资料，多选面板和4/3/2列照片。
- 实际浏览器检查16组响应式、筛选弹层及空态/失败；修正手机排序与图片资料裁切。同步页面主文档Q节、页面清单和主进度。
- 尚未修改Dimensions正式模板或业务逻辑；等待用户确认后实施，未部署。

## 第三批3B球队与赛季整改完成（2026-11-05，本地）

- W11–W14正式实施，两共用Vue模板与局部样式，AFA导航页脚、索引3/2/1、档案4/3/2照片、多选筛选面板和原关联入口；原业务请求保留，详见页面主文档R节。
- 类型/相关代码检查/格式/生产构建、13项前端测试、5项球队赛季后端测试168断言通过；实际浏览器16组响应式、筛选提交/重置、Escape焦点返回通过，修正并复核页头布局。
- W01–W14本地完成，未部署。下一批时间线等发现页仍按先设计确认推进。

## 第三批3C时间线设计交付（2026-11-05，待确认）

- W15–W17总览/年度/月度两模板完成真实页面审查和独立设计稿，延续首页AFA体系；年份图文条目、月份常显、8组分类与标签筛选面板、4/3/2列照片及原关联入口。
- 浏览器12组响应式、面板选项/键盘关闭、空态和图片失败检查通过。数据为真实公开快照，筛选与分页仅演示，不提交业务请求。
- 同步页面主文档S节和W15–W17设计待确认状态。未改正式Timeline模板，未运行正式构建/测试或部署；W01–W14本地完成状态不变。等待确认后实施。

## 第三批3C时间线整改完成（2026-11-05，本地）

- W15–W17正式实施，年份图文、月份常显、原分类/标签筛选面板、4/3/2列照片及分页/关联入口，统一AFA导航页脚。原日期范围、请求参数与业务不变，详见页面主文档T节。
- 类型/代码/格式/构建、13项前端测试、时间线与球队赛季10项后端测试341断言通过；浏览器12组响应式、真实分页/组合筛选/重置/无结果与键盘焦点通过。修正重复箭头和公共页头留白，最终截图已查看。
- W01–W17本地完成，未部署。下一批W18排行榜与W19公开用户主页先设计确认。

## 第三批3D排行榜与公开用户主页设计交付（2026-11-05，待确认）

- W18/W19独立设计稿完成，AFA榜单4/3/2列、金色名次与带标签计数、手机横滑类型；主页紧凑身份/勋章/评论，详见页面主文档U节。
- 实际浏览器检查综合/点赞及7天空态；本地无人开启公开主页，仅核对404，主页采用明确虚构设计样本，未更改隐私。两页8组响应式及示例状态通过，不表示真实用户主页已验收。
- 同步页面清单、设计规格和进度；正式模板未改、未业务测试/构建/部署，W01–W17完成状态不变，待确认再实施。

## 第三批3D排行榜与公开用户主页整改完成（2026-11-05，本地）

- 按用户最终确认，排行榜卡片去掉日期与类型/场景/年份分类摘要，只保留图片、名次、标题和互动指标；统计时间范围保留。排行榜及公开主页统一AFA风格、响应式和公共页脚，原参数、公开性与隐私规则不变，详见页面主文档V节。
- 类型/代码/格式/构建、13项前端测试、8项后端测试154断言通过；浏览器15榜单组合、四档响应式与切换通过。主页无真实公开样本，采用浏览器临时模拟payload验证正式模板的本人/非本人、空态及缺图，撤销后恢复真实404，未改变用户数据。
- W01–W19本地完成，未部署。下一批W20–W22支持本站/致谢墙/支持结果先设计确认。

## 排行榜分页补充（2026-11-05，本地）

- 按追加要求增加服务端每页20张分页，取消前台前24张截断，总上榜数为当前条件全量数量；原排名规则保持，跨页连续名次、type/window保留，切条件回第一页。复用ArchivePagination效果，详见页面主文档V1。
- 5项后端测试137断言、13项前端测试、类型/代码/格式/Pint/构建通过；实际浏览器验证首屏20张、第二页13张、#21起及禁用/切换/单页隐藏，桌面手机截图已查看。未部署。

### 排行榜仅展示前50名补充（2026-11-05，本地完成）

按用户追加要求，前台每个榜单/时间范围只展示排名前50张，仍每页20张，最多3页（20/20/10）。服务端在原公开范围、指标排序与同分规则下先截取前50，再分页；总上榜数量最高50，页3无下一页，直接请求页4不返回第51名后图片。不足50张按实际数量，后台统计列表不受影响。此要求覆盖此前全量上榜分页说明，前台继续复用ArchivePagination效果及type/window参数。

新增55张公开互动图片回归，验证三页20/20/10、连续名次1–50、总数50、第三页截止图片与无下一页、第四页空数据；排行榜6项测试227断言通过。未创建真实演示数据，未部署。

## 第四批支持页面设计交付（2026-11-05，待确认）

- W20–W22支持本站/致谢墙/支持结果独立设计稿完成，沿用AFA外观；真实方案、公开名单、手机参与说明折叠、清晰支付状态和订单资料，详见页面主文档W节。
- 实际浏览器12组响应式、16种本地示例状态及禁用/折叠检查通过。结果页游客仍跳登录，用虚构订单和文字QR占位设计，不下单、不调用支付或查询、不改隐私。
- 整页截图发生超时/视口降级，已明确截图仅视口参考，未宣称全页截图验收；真实支付全链路未验证。正式模板未改、未构建/业务测试/部署，待确认后实施。

## 支持页与致谢墙第二版设计（2026-11-05，待确认）

- 根据初稿布局反馈重设计W20/W21：支持方案提前、说明后置；致谢墙改天蓝卡头/跨接头像/身份标签成员卡与独立共建者图文区。支付结果及原业务边界保持。
- 明确累计支持5人与公开展示4人的不同口径，纠正初稿误归因于分页的说明；原公开规则、每页24条及名单顺序不改。
- 浏览器8组响应式和8种状态检查通过，视口截图已查看，详见页面主文档W4。仅更新独立设计稿和现有文档，正式页面未改，待确认。

## 支持页第三版设计修订（2026-11-05，待确认）

- 按反馈重新审查操作可识别性：支持方式改字段列表、移除支持页孤立装饰图标、参与说明明确展开/收起、致谢墙独立46px描边链接按钮，版权/公开偏好改非交互说明区。
- 浏览器四档响应式、六种支持状态、禁用/错误、键盘展开和真实致谢链接跳转通过，已查看最终桌面/手机图，详见页面主文档W5。
- 只改独立设计稿与文档，正式页面和支付逻辑未动；待用户确认。致谢墙第二版成员卡及结果页设计保持。

## 第四批支持页面整改完成（2026-11-05，本地）

- W20–W22按确认稿正式实施：支持页第三版明确按钮/字段说明/方案前置，致谢墙第二版成员卡，结果页状态与订单呈现；统一AFA导航页脚。原下单、支付查询和定时器整段比对一致，公开规则及原条数保持，详见页面主文档X节。
- 类型/代码/格式/最终构建、13项前端测试、26项赞助支付后端测试250断言通过；真实支持/致谢8组布局、链接/折叠/登录边界通过，结果状态用临时模拟资料与冻结定时器验收，支付请求0、不创建订单，不代表真实渠道全链路验收。
- W01–W22本地完成，未部署。下一批W23起站点说明/版权等阅读页先设计确认。

## 第五批站点说明阅读页设计交付（2026-11-05，待确认）

- W23–W27五页共用阅读设计完成：紧凑标题、受控正文行宽、桌面左侧导航、手机正文前明确页面切换、下架邮件入口独立按钮，沿用首页AFA体系，详见页面主文档Y节。
- 实际浏览器20组响应式及正文逐条一致性、手机键盘导航、邮箱/无邮箱检查通过；未发送邮件，未改写正文或增加申请表。
- 仅独立原型与文档更新，正式Static/Show未改、未业务测试/构建/部署。W01–W22本地完成状态不变，待确认后实施。

## 第五批阅读页面整改完成（2026-11-05，本地）

- W23–W27五页正式接入共用阅读模板：AFA导航页脚、紧凑标题、受控行宽、桌面常显侧边导航、手机正文前切换控件、明确邮件入口，原条款/字段/URL和无邮箱逻辑保持，详见页面主文档Z节。
- 类型/代码/格式/构建、13项前端测试、9项后端测试389断言通过；浏览器20组合与原文逐条比对、手机键盘切页/收起、邮箱/无邮箱恢复通过，未发送邮件或改后台设置。
- W01–W27本地完成，未部署。下一批W28起账号入口先设计确认。

## 第六批账号入口设计交付（2026-11-05，待确认）

- 完成W28–W34七个认证页面独立设计稿，统一集中式表单、AFA页头/金色主动作、手机输入区域和错误/提交状态；原字段、验证、注册开关及访问权限保持，详见页面地图AA节。
- 浏览器28组响应式、16状态、10类交互通过，五张最终整页图已查看；POST/脚本错误0。受保护页面仅标记样例，未创建账号、发送邮件或测试真实令牌链路。
- JavaScript语法及Taste源码lint通过；仅设计原型和既有文档，正式页面未修改，未构建/部署。待确认后实施。

## 第六批账号页面整改完成（2026-11-05，本地）

- W28–W34七页正式统一AFA认证布局、手机表单、密码文字显隐、字段错误关联及提交状态，复用正式页脚社媒；保留原认证字段、注册开关、令牌、权限和验证码逻辑，详见页面地图AB节。
- 类型/代码/格式/最终构建、13项前端测试、33项认证相关测试113断言通过；浏览器28组合与最终公开页12组合复核、模拟提交状态及模式切换通过。8次POST全拦截、真实转发0，未创建账号或发送邮件。
- 受保护页使用临时浏览器响应验收真实组件，不宣称真实邮件/令牌链路通过。W01–W34本地完成，未部署；下一批个人中心先设计确认。

## W35个人中心总览设计交付（2026-11-05，待确认）

- 完成独立总览设计：紧凑统计、收藏/通知前置、独立内容列、手机完整导航折叠、公开设置后置及明确动作入口，保持原字段/入口/隐私规则，详见页面地图AC节。
- 浏览器五档尺寸、24组样例状态及关键交互通过，写请求/脚本错误0；真实/me仍为游客登录边界，私人记录仅为标记样例。
- 仅更新独立原型与现有文档，正式个人中心未修改、未构建/部署，待确认后实施。

### W35个人中心设计修订（2026-11-05）

- 账号设置改为默认展开的四子项分组；手机保留外层导航折叠，并按用户明确选择完全隐藏最近收藏、通知、评论/纠错与公开设置，不提供展开入口，桌面/平板保持展示。
- 六档响应式与48组状态检查通过；仅独立设计稿和既有文档更新，正式页面未改，详见页面地图AC4。

## W35个人中心总览整改完成（2026-11-05，本地）

- 正式/me统一首页AFA风格与独立内容列；手机完全隐藏四个模块、保留导航/统计，账号设置默认展开四个子项。新导航变体只在本页启用，原设置保存逻辑与后台规则保持，详见页面地图AD节。
- 类型/代码/格式/最终构建、13项前端测试、11项后端测试181断言通过；浏览器六档及30组内容状态、动态断点、菜单/模拟保存/旧页兼容通过，两次PATCH全拦截，未写真实资料。
- 最终上部/导航视口已查看；下方表单截图受工具超时限制，仅DOM与交互核对，不称整页视觉验收。W01–W35本地完成，未部署，下一批我的收藏先设计。

## W36我的收藏页设计交付（2026-11-05，待确认）

- 独立设计稿统一AFA工作区、卡片、筛选、手机双列与分页，保留每页12条、原查询/取消/隐私规则；详见页面地图AE节。
- 浏览器六档、48组状态及关键交互通过，五类整页截图已查看；使用明确样例与公开素材，写请求0，未取消真实收藏。
- 仅设计稿与既有文档更新，正式页面未改、未构建或部署，待确认后实施。

- W36设计稿按反馈改为桌面一行四列，平板/手机继续双列；每页12条不变，浏览器复核与桌面截图更新完成，正式代码未改。

## W36我的收藏页整改完成（2026-11-05，本地）

- 正式收藏页接入桌面四列/手机双列、AFA个人中心导航、可折叠筛选、明确单条取消与统一分页，保留每页12条和原查询/CSRF/取消/权限规则，详见页面地图AF节。
- 类型/代码/格式/构建、13项前端测试、11项后端测试181断言通过；浏览器八档及48组状态、筛选分页和模拟取消通过，五类整页图已查看。两次DELETE全拦截，未取消真实收藏。
- W01–W36本地完成，未部署；受保护页面使用标记样例验收，下一批评论/纠错页先设计确认。

## W37我的评论与纠错页设计（2026-11-05，待确认）

- 提供独立响应式设计稿：单列记录、两项筛选、明确图片入口、完整纠错字段与五审核状态，沿用AFA个人中心视觉和轻动效；原每页10条、查询与业务规则保持。
- 六档48组状态及筛选分页检查通过，五张整页图已查看，JS语法与Taste源码lint通过；仅样例预览，正式代码未实施、无真实写入或部署。规格见页面地图AG节。

## W37我的评论与纠错整改完成（2026-11-05，本地）

- 正式评论记录页统一AFA个人中心视觉、响应式记录布局、明确图片按钮、非空纠错资料与五状态，复用分页和轻动效；原筛选/每页10条/审核结果/访问规则保持。
- 类型、代码、格式、构建、13项前端及11项后端测试181断言通过；浏览器六档48组合、筛选分页与五张整页截图检查通过。仅样例组件验收，无真实提交或审核操作，未部署。规格见页面地图AH节。

## W38我的举报页设计（2026-11-05，待确认）

- 提供AFA个人中心风格的响应式举报记录设计：明确状态筛选、摘要/补充/处理结果层级、图片入口与统一分页，保留每页10条和现有业务/隐私边界。
- 浏览器六档48组合、四状态筛选与分页检查通过，五张最终整页图已查看，语法与Taste源码lint通过。仅样例预览，无真实举报操作，正式代码未实施；规格见页面地图AI节。

## W38我的举报整改完成（2026-11-05，本地）

- 正式举报页统一AFA个人中心风格、状态筛选、目标摘要/补充/结果层级、图片按钮、手机布局及分页动效，原每页10条/查询/权限与隐私规则保持。
- 类型、代码、格式、构建、13项前端测试、11项后端测试181断言及浏览器六档48样例状态检查通过，五张整页图已查看；无真实举报操作，未部署。详见页面地图AJ节。

## W39通知中心设计（2026-11-05，待确认）

- 完成通知中心独立响应式设计稿：明确筛选、未读层级、独立查看/标为已读、全部已读范围与统一分页，保留原功能规则。
- 六档48样例组合和筛选/分页/模拟已读检查通过，三张最终整页图已查看；正式代码未实施，无真实通知写入，详见页面地图AK节。

## W39通知中心整改完成（2026-11-05，本地）

- 正式通知页统一AFA个人中心、明确筛选/未读/查看/已读操作、手机排版及统一分页，原每页10条和写入规则保持。
- 类型、代码、格式、构建、13项前端及11项后端测试181断言通过；浏览器六档48组合与三张整页图检查完成，4次已读PATCH均拦截模拟，无真实数据写入，未部署。详见页面地图AL节。

## W40我的赞助设计（2026-11-05，待确认）

- 完成我的赞助独立响应式设计：身份/公开偏好分区、桌面订单表格/手机卡片、统一分页及明确保存反馈，保留原支付与隐私边界。
- 六档48样例组合及保存/分页/链接条件检查通过，三张整页图已查看；无真实公开资料写入、无支付，正式代码未实施。详见页面地图AM节。

## W40我的赞助整改完成（2026-11-05，本地）

- 正式赞助页统一AFA个人中心、公开偏好/保存反馈、桌面订单表格与手机卡片，补齐统一分页；保留原字段、每页10条、权限与支付条件。
- 类型/代码/格式/构建、13项前端及37项后端测试431断言通过；浏览器六档48样例组合与三张整页图检查完成，两次保存均拦截，无真实资料写入或支付，未部署。详见页面地图AN节。

## W41我的勋章设计（2026-11-05，待确认）

- 提供统一AFA个人中心风格的勋章设计：当前佩戴、响应式图文收藏、筛选/进度和明确操作，保留永久获得、多枚不限量与原资格规则。
- 浏览器六档48样例组合、筛选和模拟佩戴检查通过，三张整页图已查看；无真实勋章写入，正式代码未实施。详见页面地图AO节。

## W41我的勋章整改完成（2026-11-05，本地）

- 正式勋章页统一AFA个人中心、当前佩戴/图文收藏/筛选与进度、手机布局及佩戴反馈，原图标资产/永久保留/不限量/权限保持。
- 类型、代码、格式、构建、13项前端及16项后端测试209断言通过；浏览器六档42样例组合与三张整页图已检查，三次佩戴PATCH均拦截，无真实资产改动，未部署。详见页面地图AP节。

## W42勋章大厅设计（2026-11-05，待确认）

- 提供统一AFA风格的勋章目录、搜索和获取条件设计，保持原实际功能；修正任务表对分类/个人进度/状态的错误描述。
- 浏览器六档36样例组合、搜索/管理入口与三张最终截图检查通过，正式代码未实施，无真实勋章操作。详见页面地图AQ节。

## W42勋章大厅整改完成（2026-11-05，本地）

- 正式大厅统一AFA导航、响应式图文目录、获取条件、搜索反馈及明确手机管理入口，保留原搜索/资产/权限，不新增业务功能。
- 类型、代码、格式、构建、13项前端及16项后端测试209断言通过；浏览器六档36样例状态及三张整页图已检查，无真实数据写入、未部署。详见页面地图AR节。

## W43图片投稿设计（2026-11-05，待确认）

- 提供统一AFA风格的资格/上传/历史投稿设计，明确上传与提交审核、单张/整批资料和受限/错误状态，保留原权限、上传限制和每页20条。
- 浏览器六档72样例组合、四张最终整页图检查通过；无真实申请、上传或撤回，正式代码未实施。详见页面地图AS节。

- W43设计修订（2026-11-05）：按用户要求，桌面/手机历史投稿整体默认折叠，显示记录数，点击后展开；展开内的原查看/编辑/撤回/20条分页保持，上传区不变。六档默认折叠/展开/收起/分页检查通过，设计桌面手机图已更新，正式页面尚未实施。详见页面地图AS2节。

## W43图片投稿整改完成（2026-11-05，本地）

- 正式投稿页统一AFA布局与响应式表单，桌面/手机历史默认折叠，翻页保持展开，保留原上传/审核/编辑/撤回及20条分页规则。
- 类型、代码、格式、构建、13项前端及39项后端测试369断言通过；浏览器六档36样例状态、编辑/分页/模拟上传提交检查完成，三张整页图已查看。两次POST拦截，无真实文件或投稿操作，未部署。详见页面地图AT节。


### 2026-11-06 · 分享推广设计（待确认）

- 完成W44隔离原型与响应式、状态、弹窗演示检查，正式代码及推广统计未改，待确认后实施。


### 2026-11-06 · W44分享推广整改完成

- 统一首页蓝白金及个人中心框架，明确分享按钮、50人有效进度、勋章入口与分组规则；手机纵向适配。
- 原分享组件和后端业务不变；构建、类型、相关测试与隔离浏览器检查通过，未部署。


### 2026-11-06 · W45个人资料设计（待确认）

- 完成账号设置剩余4页盘点及个人资料隔离原型，正式账号功能未修改。


### 2026-11-06 · W45个人资料整改完成

- 统一个人资料页的蓝白金、导航及表单层级，优化头像裁剪与删除确认外观，保留全部原流程。
- 新设置框架按页启用，原其他设置页暂不切换。资料页GET增加本人导航只读数据。
- 构建、类型、13前端及14设置后端测试通过；隔离浏览器检查通过，整页截图回退如实记录，未部署。


### 2026-11-06 · W46修改密码设计（待确认）

- 完成密码页隔离原型，统一账号设置框架及表单状态，正式密码功能未修改。


### 2026-11-06 · W46修改密码整改完成

- 统一密码页导航、配色、分组、手机布局与提交反馈；原密码规则及接口不变。
- 使用原生密码输入，确保原Form成功/失败清空机制不被输入组件恢复旧值。
- 类型、构建、13前端及14设置测试通过，隔离浏览器模拟及整页截图复核通过，未部署。


### 2026-11-06 · W47外观设置设计（待确认）

- 完成浅色、深色、跟随系统三模式设计原型及响应式、键盘、系统切换和整页截图检查；正式主题功能未修改。


### 2026-11-06 · W47外观设置整改完成

- 三模式改为响应式单选卡，统一首页配色及设置导航，显示当前与系统状态，保持点击即生效。
- 原主题存储逻辑不改，修复设置区域深色背景间隙；构建、类型、13前端及15设置测试、隔离浏览器复核通过，未部署。


### 2026-11-06 · W48安全验证设计（待确认）

- 完成最后一页及二维码/验证码/恢复码等状态原型，继承首页风格。浏览器72组合与整页图已复核，正式安全功能未修改。


### 2026-11-06 · 个人中心恢复公共页脚

- 个人中心及全部子页面显示首页同款页脚，复用原链接和社交按钮，桌面/手机一致。
- 构建、类型、13前端测试及10模板30个浏览器组合、无刷新页面切换检查通过，账号功能不变，未部署。


### 2026-11-06 · 修复个人中心页脚上方黑条

- 修复个人中心及全部子页面底部外边距折叠露出深色背景的问题，保留原布局间距。
- 构建、13前端测试及60组浅深背景/宽度检查通过，未部署。
