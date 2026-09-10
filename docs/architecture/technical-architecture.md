# 技术架构与选型

## 1. 架构结论

项目采用 Laravel 单体应用起步，同仓维护后端、前台和后台。前台使用 Inertia.js + Vue 3 + TypeScript + Tailwind CSS；后台管理从第一版开始使用 Filament；图片存储通过 Laravel 文件系统抽象管理，支持本地存储和腾讯云 COS 切换，生产环境优先 COS/CDN；图片处理使用腾讯云数据万象；缓存和队列使用 Redis。

这个选择的核心原因是：前台图库需要更顺滑的筛选、灯箱、收藏、评论和移动端交互；后台又需要快速、稳定地生成表单、表格、批量操作和权限管理。Inertia 负责前台体验，Filament 负责后台效率，两者都在 Laravel 同一个项目内运行，避免前后端分仓带来的部署和协作复杂度。

## 当前生效规则（2026-09-09）

- 图片处理模块当前只负责派生图、EXIF/metadata、哈希、OCR 和相似图索引；智能标签不再生成、不再展示、不再参与任务统计。
- 派生图任务在 local 模式由 PHP GD 生成 WebP，在 COS 模式由腾讯云数据万象生成 WebP；两种模式使用相同的 display_key 和 thumbnail_key。
- 上传任务只负责记录上传结果和处理状态；图片元数据编辑、分类、标签和发布统一由图片管理提供。
- 单独图片不要求加入相册；无相册图片发布时只校验 生涯阶段、年份、场景 3 个必选主分类。
- 每次上传都会创建 `metadata`、`hash` 和 `datawanxiang_derivatives` 处理任务；派生图任务根据执行时的存储设置选择 PHP GD 或腾讯云数据万象，前台统一读取 `display_key`/`thumbnail_key`。
- `PhotoProcessingService` 自动发布前按任务 ID 倒序、每种类型只保留最新任务记录，避免历史失败任务覆盖成功重试；没有展示图或缩略图时发布条件不成立。
- 队列 Worker 是代码更新后的长驻运行时，发布代码或配置缓存后必须重启；后台仅修改数据库中的存储设置不要求重启 Worker，但不应在有待处理任务时随意切换存储介质。
- 首页精选相册由相册 `is_featured`/`featured_at` 驱动，未手动设置封面时取相册内最新公开图片；首页分类导航只允许配置公开子分类。

## 2. 为什么不用 WordPress / Piwigo / Directus

| 方案 | 结论 | 原因 |
|---|---|---|
| WordPress | 不采用 | 博客/CMS 历史包袱重，图库、轻社交、支付、数据万象二开会越来越别扭 |
| Piwigo | 不采用为主架构 | 适合传统图库，但会员、评论、支付、小程序 API 和复杂业务扩展不够顺 |
| Directus | 暂不采用 | 适合资料后台，但用户、评论、支付、风控仍需要独立业务后端 |
| Laravel + Inertia + Vue + Filament | 采用 | 更适合图库前台交互 + 轻社交 + 赞助支付 + 后台权限 + 队列任务 |

## 3. 主要模块

### 图片档案模块

负责图片上传、元数据、相册、标签、来源链接、版权状态、审核状态和水印状态。

### 图片处理模块

负责生成缩略图、展示图、EXIF、文件哈希、OCR、相似图索引。P1-11 已完成本地基础 metadata/hash 处理和精确 SHA-256 重复提示；数据万象派生图、OCR 和相似图后台辅助适配已完成，智能标签当前停用；处理任务支持有限重试、失败记录、后台异步/批量恢复和上传任务统计，图片质量评分不纳入项目。所有耗时任务进入队列。

### 检索模块

P0 使用 MySQL 索引和简单全文搜索。P2 接入 Meilisearch 或 Typesense，支持更快的组合筛选。

### 用户与权限模块

负责游客、普通用户、支持者、编辑员、管理员权限。

### 轻社交模块

负责收藏、点赞、评论、分享、举报、审核、封禁、互动榜单、勋章、成就任务和站内通知。

### 赞助模块

负责赞助订单、支付记录、支持者身份和支持者展示。P1-9 当前只完成模拟支付与后台手动处理；微信支付 / 网页支付、真实支付回调、验签、退款、发票和对账后续独立确认。

### 后台管理模块

使用 Filament Resource 管理图片、相册、分类、标签、用户、评论、举报、互动统计、勋章、订单、处理任务和系统设置。

### 前台交互模块

使用 Inertia.js + Vue 3 实现公开网站页面。P0 / Phase 1 已完成首页、图库列表、搜索页、相册列表、相册详情、专题列表、专题详情和图片详情；P1 已完成收藏、点赞、分享记录、分享弹层、微博跳转、微信二维码引导、评论、排行榜、用户中心、通知中心、勋章基础、支持者页面和站点说明页面。时间轴、公开用户个人主页、真实微信 JS-SDK / 第三方分享 API、分享海报、短链接、灯箱、相似图推荐和复杂社交动态流后续独立确认。前台以 Tailwind CSS 定制组件为主，不引入重型通用 UI 组件库。

## 4. 技术栈

| 层级 | 选型 |
|---|---|
| 后端 | Laravel 12 |
| 前台桥接 | Inertia.js 3 |
| 前台框架 | Vue 3 + TypeScript |
| 前台样式 | Tailwind CSS |
| 后台 | Filament 5 |
| 前台图库交互 | PhotoSwipe、Swiper；瀑布流/虚拟滚动按需评估 |
| 数据库 | MySQL 8.0 |
| 缓存/队列 | Redis |
| 图片存储 | Laravel 文件系统抽象；支持本地存储和腾讯云 COS，生产优先 COS/CDN |
| 图片智能处理 | 腾讯云数据万象 |
| 搜索 | MySQL 起步，后续 Meilisearch/Typesense |
| 部署 | Ubuntu 24.04 + 宝塔 LNMP |
| 进程守护 | Supervisor |
| 测试 | Pest 或 PHPUnit，优先 Feature Tests |

参考：

- Laravel Starter Kits：https://laravel.com/index.php/starter-kits
- Laravel Frontend / Inertia：https://laravel.com/framework/docs/12.x/frontend
- Inertia Laravel setup：https://inertiajs.com/docs/v3/installation/server-side-setup
- Inertia SSR：https://inertiajs.com/docs/v3/advanced/server-side-rendering
- Laravel Queues：https://laravel.com/framework/docs/12.x/queues
- Laravel File Storage：https://laravel.com/framework/docs/filesystem
- Filament Getting Started：https://filamentphp.com/docs/5.x/getting-started
- Filament Users：https://filamentphp.com/docs/5.x/users/overview
- PhotoSwipe：https://photoswipe.com/getting-started/
- Swiper：https://swiperjs.com/

## 5. 组件策略

### 前台组件

前台不使用 Naive UI、Arco Design 这类完整后台型组件库。原因是图库前台需要内容优先、视觉可控、加载快，重型组件库容易把页面做成管理系统风格。

前台默认策略：

- 基础组件：使用 Vue + Tailwind 自定义，例如按钮、筛选项、标签、图片卡片、抽屉、弹窗。
- 图片灯箱：使用 PhotoSwipe，适合大图浏览、手势缩放、左右切图；必须提供宽高，避免布局跳动。
- 轮播/专题：使用 Swiper，仅用于首页精选、专题相册、图片详情相关推荐，不滥用于图片列表。
- 图片网格：P0 优先 CSS Grid；若后续图片比例差异大，再评估 Macy.js 或自研 masonry；若一次渲染大量图片，再评估虚拟滚动。

### 后台组件

后台使用 Filament。Filament 负责：

- 表格
- 表单
- 筛选
- 批量操作
- 详情页
- 权限控制
- 后台资源导航

后台不使用 Naive UI / Arco Design / shadcn-vue 作为第一选择。只有当未来出现 Filament 无法满足的高度定制后台页面时，才通过 ADR 重新评估。

## 6. 请求流程

### 图片上传流程

1. 编辑员在后台批量选择图片，可选择单独上传或关联到某个相册。
2. Laravel / Filament 校验文件类型、大小和权限。
3. 系统按 `YYYYMMDD-6位大写字母.原扩展名` 自动重命名文件，并写入当前启用的存储驱动；本地存储和腾讯云 COS 都可作为驱动，生产环境优先 COS/CDN。
4. 创建 `photo_upload_batches` 批次记录，并为每个成功文件写入 `photos` 记录，状态为 `draft` 草稿/待整理。
5. 相册内上传形成的图片记录自动写入相册关系，并同步相册已选择的分类；单独上传图片在整理/发布前补齐生涯阶段、年份、场景 3 个必选主分类，其他主分类可选。
6. Phase 1 上传时不做重复检测、哈希拦截、感知哈希检测或相似图检测；同一视觉图片重复上传视为多条独立图片记录。
7. 展示图、缩略图、EXIF、哈希等处理后置到异步任务；OCR 仅按需触发，智能标签不再生成，以上处理均不阻塞基础上传发布闭环；图片质量评分不纳入项目。
8. 图片编辑、分类、批量修改和发布统一在图片管理中完成；上传任务页只保留上传结果、失败原因和处理状态记录。

### 前台浏览流程

1. 用户访问相册/搜索页。
2. Laravel 路由和 Controller 查询已发布图片。
3. Controller 返回 Inertia 页面和页面 props。
4. Vue 页面渲染筛选状态、图片网格和交互。
5. 生产环境图片流量优先走 COS/CDN；本地开发或过渡阶段可走本地存储。

### 评论流程

1. 登录用户提交评论。
2. 系统做频率限制和敏感词检测。
3. 新用户评论进入待审核。
4. 审核通过后展示。
5. 用户可举报，管理员处理。

### 赞助流程

1. 用户选择赞助金额。
2. Laravel 创建订单。
3. P1-9 当前使用模拟支付或后台手动处理订单。
4. 模拟支付成功后幂等更新订单。
5. 发放支持者身份。
6. 真实微信支付下单、回调验签、退款、发票和对账后续独立确认。

## 7. 架构原则

1. 图片文件与业务数据分离：文件存储在当前启用的存储驱动中，数据库存元数据和存储 key；生产环境优先 COS/CDN。
2. 慢任务异步化：OCR、相似图、压缩、批处理都进队列。
3. 前台不直连密钥服务：所有云服务调用经后端。
4. 先单体，后拆分：不要过早引入微服务。
5. 接口面向小程序预留：核心查询能力应可 JSON 化。
6. 前台和后台职责分离：前台用 Inertia/Vue 做用户体验，后台用 Filament 做管理效率。
7. 组件按需引入：图库交互库只服务明确场景，不把组件库当设计系统。


## 8. 已接受架构决策

### ADR-0001：采用 Laravel + Inertia + Vue + Filament

状态：Accepted。

背景：项目最初讨论过 WordPress、Piwigo、Directus、Payload、Laravel + Filament 等方案。需求已经从纯图库明确为“图库 + 轻社交 + 赞助支持 + 后台权限 + 图片智能处理 + 后续小程序”。

决策：采用 Laravel 单体应用作为主架构；前台使用 Inertia.js + Vue 3 + TypeScript + Tailwind CSS；后台使用 Filament。

理由：Laravel 适合用户、权限、评论、支付、队列、文件、API 等完整业务系统；Inertia 让前台可以用 Vue 做更顺滑的图库交互，同时继续使用 Laravel 路由、Controller、认证和部署方式；Filament 能快速搭建后台；单体应用降低部署和调试复杂度；后续可为小程序提供 API。

不采用：WordPress 过于臃肿，业务模型会被 CMS 结构拖累；Piwigo 适合传统图库，但不适合轻社交、支付和复杂业务扩展；Directus 适合内容后台，但普通用户、评论、支付仍需独立业务后端。

后果：项目需要实际开发而不是安装模板即可完成；第一版必须控制范围；部署时需要 PHP/Composer 和 Node/npm；若后续启用 Inertia SSR，需要额外用 Supervisor 守护 SSR 进程。
