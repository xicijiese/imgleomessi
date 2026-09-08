# Changelog

## 2026-09-08

### Changed
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
- 新增 `users.profile_public`、`users.profile_bio`、`PublicUserProfile`、`PublicUserProfileController`、`Users/Show.vue` 和 `PublicUserProfileTest`；公开页只展示昵称、注册月份、公开简介、公开支持者摘要、当前佩戴 / 已获公开勋章和已发布普通评论摘要。
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
- 完成 P1-9 支持者赞助闭环：新增 `/support`、`/support/result`、`/supporters`、`/me/sponsorships` 前台页面，支持赞助方案展示、模拟订单创建、模拟支付成功、个人赞助记录和支持者墙公开展示开关。
- 新增 `/admin/sponsorship-plans`、`/admin/sponsorship-orders`、`/admin/payment-logs` 后台管理入口，支持赞助方案维护、订单状态处理和支付日志排障。
- 新增 `sponsorship_plans`、`sponsorship_orders`、`payment_logs`、`supporter_profiles` 表，并为 `users` 增加 `supporter_until`；P1 阶段只做模拟支付和后台手动处理，不接真实第三方支付 API。
- 新增 `SponsorshipPlanSeeder`，本地演示 Seeder 会生成 4 个模拟支持者赞助订单，方便查看支持页、支持者墙和个人中心赞助记录。

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
- php artisan db:seed --class=LocalGalleryDemoSeeder 通过，已生成 30 张图片、4 个相册、4 个首页专题、60 个图片处理任务、4 个模拟用户、15 条评论/纠错记录、124 条互动排行记录、4 个支持者赞助订单和 17 个用户勋章记录。
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
- php artisan db:seed --class=LocalGalleryDemoSeeder 通过，已生成 30 张图片、4 个相册、4 个首页专题、4 个模拟用户、15 条评论/纠错记录、124 条互动排行记录、4 个支持者赞助订单和 17 个用户勋章记录。
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
