# 技术架构与选型

## 1. 架构结论

项目采用 Laravel 单体应用起步，同仓维护后端、前台和后台。前台使用 Inertia.js + Vue 3 + TypeScript + Tailwind CSS；后台管理从第一版开始使用 Filament；图片存储使用腾讯云 COS；图片处理使用腾讯云数据万象；缓存和队列使用 Redis。

这个选择的核心原因是：前台图库需要更顺滑的筛选、灯箱、收藏、评论和移动端交互；后台又需要快速、稳定地生成表单、表格、批量操作和权限管理。Inertia 负责前台体验，Filament 负责后台效率，两者都在 Laravel 同一个项目内运行，避免前后端分仓带来的部署和协作复杂度。

## 2. 为什么不用 WordPress / Piwigo / Directus

| 方案 | 结论 | 原因 |
|---|---|---|
| WordPress | 不采用 | 博客/CMS 历史包袱重，图库、轻社交、支付、数据万象二开会越来越别扭 |
| Piwigo | 不采用为主架构 | 适合传统图库，但会员、评论、支付、小程序 API 和复杂业务扩展不够顺 |
| Directus | 暂不采用 | 适合资料后台，但用户、评论、支付、风控仍需要独立业务后端 |
| Laravel + Inertia + Vue + Filament | 采用 | 更适合图库前台交互 + 轻社交 + 赞助支付 + 后台权限 + 队列任务 |

## 3. 主要模块

### 图片档案模块

负责图片上传、元数据、相册、标签、来源、版权状态、审核状态。

### 图片处理模块

负责生成缩略图、展示图、EXIF、文件哈希、OCR、质量评分、相似图索引。所有耗时任务进入队列。

### 检索模块

P0 使用 MySQL 索引和简单全文搜索。P2 接入 Meilisearch 或 Typesense，支持更快的组合筛选。

### 用户与权限模块

负责游客、普通用户、支持者、编辑员、管理员权限。

### 轻社交模块

负责收藏、点赞、评论、举报、审核、封禁。

### 赞助模块

负责赞助订单、微信支付/网页支付、支付回调、支持者身份。

### 后台管理模块

使用 Filament Resource 管理图片、相册、标签、用户、评论、订单、处理任务。

### 前台交互模块

使用 Inertia.js + Vue 3 实现公开网站页面，包括首页、图库列表、搜索筛选、相册页、图片详情页、收藏、评论和支持者页面。前台以 Tailwind CSS 定制组件为主，不引入重型通用 UI 组件库。

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
| 图片存储 | 腾讯云 COS |
| 图片智能处理 | 腾讯云数据万象 |
| 搜索 | MySQL 起步，后续 Meilisearch/Typesense |
| 部署 | CentOS + 宝塔 LNMP |
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

1. 编辑员在后台上传图片。
2. Laravel 校验文件类型、大小、权限。
3. 文件写入 COS。
4. 写入 `photos` 记录，状态为 `pending_analysis`。
5. 派发队列任务。
6. 队列读取 EXIF、计算哈希、生成缩略图、调用数据万象。
7. 分析结果写入数据库。
8. 编辑员确认元数据后发布。

### 前台浏览流程

1. 用户访问相册/搜索页。
2. Laravel 路由和 Controller 查询已发布图片。
3. Controller 返回 Inertia 页面和页面 props。
4. Vue 页面渲染筛选状态、图片网格和交互。
5. 图片流量走 COS/CDN。

### 评论流程

1. 登录用户提交评论。
2. 系统做频率限制和敏感词检测。
3. 新用户评论进入待审核。
4. 审核通过后展示。
5. 用户可举报，管理员处理。

### 赞助流程

1. 用户选择赞助金额。
2. Laravel 创建订单。
3. 调微信支付下单。
4. 用户支付。
5. 微信支付回调到后端。
6. 后端验签、幂等更新订单。
7. 发放支持者身份。

## 7. 架构原则

1. 图片文件与业务数据分离：COS 存文件，数据库存元数据。
2. 慢任务异步化：OCR、相似图、压缩、批处理都进队列。
3. 前台不直连密钥服务：所有云服务调用经后端。
4. 先单体，后拆分：不要过早引入微服务。
5. 接口面向小程序预留：核心查询能力应可 JSON 化。
6. 前台和后台职责分离：前台用 Inertia/Vue 做用户体验，后台用 Filament 做管理效率。
7. 组件按需引入：图库交互库只服务明确场景，不把组件库当设计系统。
