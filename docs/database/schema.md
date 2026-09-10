# 数据库设计文档

数据库以 MySQL 8.0 为默认实现。字段类型可在 Laravel migration 中按实际需要调整。本设计是开发前逻辑模型，不是最终 migration 代码。

## 开发阶段说明

本文档记录项目的长期逻辑模型，不代表所有表都会在第一阶段同时开发。

当前已落库表先覆盖图库核心、首页配置、站点合规页依赖和 P1 图片互动、评论/纠错、审核举报、用户中心通知、互动排行榜、支持者赞助基础、勋章 / 成就基础、图片入库队列与后台重复图提示，用于完成图片录入、管理、浏览、首页展示、基础检索、搜索运营、收藏、点赞、分享记录、评论提交、图片信息补充/纠错、举报处理、封禁、站内通知、排行榜、模拟赞助闭环、基础勋章成就闭环、图片基础分析任务和精确哈希重复提示：

- photos
- photo_upload_batches
- albums
- categories
- album_category
- album_photo
- photo_category
- tags
- photo_tag
- settings
- search_recommendations
- search_queries
- photo_favorites
- photo_likes
- photo_shares
- comments
- reports
- sensitive_words
- notifications
- sponsorship_plans
- sponsorship_orders
- payment_logs
- supporter_profiles
- badges
- user_badges
- photo_analysis_results
- processing_jobs

球队、赛事、赛季、年份、比赛等通过 8 个默认主分类及其子分类承载，不单列独立足球资料表；对手名称统一作为普通标签维护。

Phase 1 明确不创建以下独立足球资料表：

- teams
- competitions
- matches

当前暂不创建以下后续功能表：

- similar_groups

已明确后续规划但不属于 P1-4 / P1-5 已完成基础切片的互动扩展：分享到微信/微博等第三方 API、分享海报生成、短链接、复杂社交动态流、后台互动管理、异常刷赞处理和排行榜管理。前台公开图片详情默认下载按钮永久不作为默认能力；分享返利默认不做，除非后续重新立项并完成合规确认。

users 表使用 Laravel/Fortify 当前已生成的基础结构。P1-6 已扩展用户状态和封禁字段，P1-9 已扩展 `supporter_until` 支持者有效期；角色、手机号等字段后续在权限或账号资料阶段再扩展。
## 当前生效规则（2026-09-09）

- photo_upload_batches 继续保留为上传任务记录，note 可记录失败文件和失败原因；该表不承担图片编辑。
- processing_jobs 不再创建智能标签任务；历史 labels 任务和 ci_labels_json 字段仅保留兼容，不参与当前发布和检索逻辑。
- 图片编辑和批量修改统一通过图片管理完成；单独图片可不加入相册。
- 每张新上传图片都会创建展示图/缩略图处理任务；local 使用 PHP GD，COS 使用数据万象，成功后分别写入 `display_key` 和 `thumbnail_key`。展示图最大 2048×2048，缩略图最大 600×600，统一 WebP。
- `processing_jobs` 允许保留历史失败记录；自动发布判断同一任务类型时按 `id` 取最新记录，成功重试不得被旧失败记录覆盖。
- 图片只有在 metadata、hash、最新派生图任务完成且 `display_key`/`thumbnail_key` 均存在时才允许自动或手动发布；OCR 和相似图任务不属于发布阻塞条件。

## Phase 1 相册、分类与标签模型决策

Phase 1 采用“相册/专题 + 8 个默认主分类（其中 3 个必选）+ 子分类 + 多标签”模型。

- 主分类：`categories.parent_id = null`，Phase 1 默认保留 `生涯阶段`、`赛事`、`赛季`、`年份`、`场景`、`图片类型`、`人物关系`、`来源平台` 8 个资料维度；其中 `生涯阶段`、`年份`、`场景`为发布必选主分类。
- 主分类集合：Phase 1 仍以 8 个默认资料维度为主，不开放随意新增根分类；管理员可以在每个主分类下持续新增、编辑和排序子分类。若未来要新增根分类，需先更新产品文档、数据库约束和历史数据策略。
- 子分类：`categories.parent_id = 主分类 ID`，例如 `阿根廷国家队`、`世界杯`、`2022年`、`球场内`、`社媒图`。
- 子分类管理：后台允许在 8 个默认主分类下自由新增、编辑和排序子分类。
- 本次分类字典已由 `GalleryTaxonomySeeder` 幂等写入数据库；该 Seeder 是当前预置分类清单的唯一来源。用户指定的子分类为：生涯阶段（纽维尔老男孩、少年时期、巴萨、巴黎、迈阿密国际、阿根廷国家队、阿根廷国青队、阿根廷国奥队、退役后）；赛事（西甲、欧冠、国王杯、西超杯、世俱杯、欧超杯、法甲、法国杯、法超杯、世界杯、美洲杯、友谊赛、世预赛、奥运会、世青赛、欧美杯、美职联、北美联赛杯、美公开杯、中北美冠军杯、美墨超级杯）；赛季（04/05赛季至22/23赛季、2023赛季、2024赛季、2025赛季、2026赛季）；年份（2003年之前、2003年至2028年、2028年之后）；场景（比赛、训练、发布会、更衣室、颁奖典礼、游行庆典、球迷互动、商业活动、家庭生活、社媒照片、球迷街拍、广告写真）；图片类型（比赛图、训练图、庆祝图、领奖图、合照、单人照、壁纸、海报、截图、新闻图、社媒图）；人物关系（队友、教练、家人、对手、名人合影）；来源平台（官方、媒体、社媒、球迷投稿、截图、未知来源）。每个主分类另预置 1 个 `待补充` 子分类。生产同步时只执行该 Seeder，不执行完整 `DatabaseSeeder`。
- 相册/专题：用于批量组织一组图片，例如 `2022世界杯决赛`、`2022世界杯夺冠之路`。
- 相册默认分类：新建相册时，必须从 3 个必选主分类中分别选择一个子分类；赛事、赛季、图片类型、来源平台等主分类可选，通过 `album_category` 实现。
- 图片相册关系：一张图片可以加入一个或多个相册，通过 album_photo 实现。
- 相册内图片分类：上传到相册形成的图片记录，完全同步该相册已选择的分类，并写入 photo_category；相册内图片不额外手动补充分类。
- 单独上传图片分类：图片不属于相册时，必须从 3 个必选主分类中分别选择一个子分类；其他主分类可选，并写入 `photo_category`。
- 分类兜底：每个主分类必须预置 `待补充` 子分类，用于资料暂时不完整、需要后续考古补充的图片；信息不知道时也必须选择对应主分类下的 `待补充`，不能留空。
- 分类修正：若后续用户在评论或其他反馈中补充了可靠信息，管理员或编辑可以将 `待补充` 修改为已有子分类，或先新增子分类再重新选择。
- 标签：用于描述更细粒度的信息，例如 `进球`、`庆祝`、`高清`、`捧杯`，通过 `photo_tag` 实现；图片标签可以为空。
- 标签管理：后台允许自由新增、编辑和排序普通标签；标签没有类型字段，图片可以挂多个标签，也可以不挂标签。

前台可以把主分类渲染为筛选分组，把子分类渲染为筛选按钮，体验上符合“分类 + 标签”的理解。相册用于专题浏览和批量上传，标签用于补充不是每张图片必定包含的细节；没有标签的图片仍然可以通过主分类、相册、标题等方式检索。

## Phase 1 重复上传与相似图规则

Phase 1 上传流程不做重复图片检测，也不阻止疑似重复图片入库。

- 不根据文件名判断重复。
- 不根据上传人判断重复。
- 不根据标题判断重复。
- 不在上传时做 `sha256` 拦截。
- 不在上传时做感知哈希检测。
- 不在上传时做相似图检测。
- 同一视觉图片如果被多次上传，默认视为多条独立图片记录。
- 同一视觉图片如果上传到不同相册，各自跟随所在相册的分类，各自维护标题、说明和标签。
- 相似图去重只作为后续后台管理能力，用于管理员日后筛选、比对、合并、删除或保留相似图片。

哈希、感知哈希和相似图组不进入图片核心模型切片，后续相似图治理或 AI 分析切片再补字段；图片质量评分明确不纳入项目；Phase 1 上传流程仍不做重复检测或相似图拦截。

## Phase 1 批量上传与整理规则

Phase 1 必须支持“批量上传到相册”。

推荐后台流程：

1. 新建相册/专题。
2. 为相册补齐三个必选主分类下的子分类，其他主分类可选。
3. 在相册中批量上传多张图片。
4. 系统为新图片自动写入相册关系和相册分类。
5. 上传完成后进入批量整理页面。
6. 管理员或编辑逐图快速补充标题、说明、标签和发布状态；图片说明和标签都可以为空。
7. 一次保存本批次整理结果。

批量整理页面至少支持编辑：

- 图片标题，不允许为空；默认使用系统重命名后的文件名。
- 图片说明，可为空。
- 图片标签，可为空。
- 发布状态。

相册内图片的分类不在批量整理页面中单独编辑，分类由相册统一决定。

图片发布状态规则：

- 批量上传或单独上传后的新图片，默认进入 `draft` 草稿/待整理状态。
- 草稿/待整理图片不在前台公开展示。
- 管理员或编辑在批量整理页面或图片编辑页点击发布后，图片立即进入 `published` 已发布状态。
- 图片发布时写入 `published_at`。
- 已发布图片对前台用户可见。
- 单独上传且不属于任何相册的图片，只要状态为 `published` 且三个必选主分类已选满，也应在前台图片列表、分类检索和首页最新照片中展示。

图片文件命名与标题规则：

- 图片上传后由系统自动重命名文件，不使用用户上传时的原始文件名作为存储文件名。
- 重命名格式为 `YYYYMMDD-6位大写字母.原扩展名`，例如 `20260825-ABCDEF.jpg`。
- 数据库保留原始文件名，用于后续追溯线索。
- 图片标题默认使用系统重命名后的文件名，因此正常上传后不会出现空标题。
- 管理员或编辑发布前可以修改标题，也可以不修改；如果人为清空标题，后台不允许保存。
- 系统生成的存储文件名不提供后台手动修改入口。

相册封面规则：

- 管理员或编辑可以为相册手动选择封面图。
- 手动封面只能选择该相册内的 `published` 已发布图片。
- 如果没有手动选择封面图，系统默认使用相册内最新上传且可公开展示的 `published` 图片作为封面。
- 相册封面只在前台读取时计算，不写入手动封面字段；新增更晚的公开图片后，封面会自动切换为最新图片。

相册发布状态规则：

- 新建相册默认进入 `draft` 草稿状态，用于先上传和整理图片。
- 只有 `published` 已发布相册在前台公开展示。
- 相册发布前必须至少包含 1 张 `published` 已发布图片；否则不允许发布，并提示管理员或编辑先上传并发布图片。
- 已发布相册的前台页面只展示 `published` 已发布图片，仍处于 `draft` 草稿/待整理状态的图片不展示。
- 已发布相册必须始终至少保留 1 张 `published` 已发布图片；如果把图片改为草稿、归档、删除或移出相册会导致该相册没有已发布图片，后台必须阻止操作，并提示先将相册改为 `draft` 草稿或 `hidden` 隐藏。
- `hidden` 隐藏状态用于临时下架或不希望前台展示的相册。
- 相册发布时写入 `published_at`。`is_featured` 表示是否推荐到首页精选相册，`featured_at` 记录最近一次推荐时间；只有已发布且包含公开图片的相册才能被首页展示，取消精选时清空 `featured_at`。

## 1. 用户与权限

### users

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 用户 ID |
| name | varchar | 昵称 |
| email | varchar nullable unique | 邮箱 |
| phone | varchar nullable unique | 手机号 |
| password | varchar nullable | 密码哈希 |
| avatar_url | varchar nullable | 头像 |
| role | enum | `admin`、`editor`、`user` |
| supporter_until | datetime nullable | 支持者有效期 |
| status | enum | `active`、`muted`、`banned` |
| email_verified_at | datetime nullable | 邮箱验证时间 |
| last_login_at | datetime nullable | 最近登录 |
| created_at / updated_at | timestamps | 时间戳 |

### supporter_profiles

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| user_id | fk users | 用户 |
| display_name | varchar nullable | 支持者展示名 |
| show_publicly | boolean | 是否展示在支持者墙 |
| total_amount_cents | int | 累计赞助金额，单位分 |
| badge_level | varchar | 支持者等级 |
| last_supported_at | timestamp nullable | 最近支持时间 |
| created_at / updated_at | timestamps | 时间戳 |

## 2. 图库核心

### photos

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 图片 ID |
| uuid | char(36) unique | 外部引用 ID |
| title | varchar | 图片标题，不允许为空；默认使用系统重命名后的文件名 |
| description | text nullable | 图片说明，可为空；用于补充背景、考古线索、来源备注等长文本 |
| original_filename | varchar nullable | 用户上传时的原始文件名，用于追溯 |
| stored_filename | varchar nullable | 系统重命名后的文件名，格式为 `YYYYMMDD-6位大写字母.原扩展名`；手工补录或历史导入可为空 |
| taken_at | datetime nullable | 拍摄时间 |
| event_date | date nullable | 事件日期 |
| source_url | varchar(2048) nullable | 图片来源链接；来源平台作为 8 个默认主分类中的来源平台子分类维护 |
| copyright_status | enum | `unknown` 待确认、`credited` 已标注来源、`restricted` 受限使用、`remove_requested` 请求下架 |
| watermark_status | enum | unknown 未确认、none 无水印、present 有水印；P1-15 用于前台高级筛选和后台维护 |
| status | enum | `draft` 草稿/待整理、`published` 已发布、`archived` 已归档 |
| publish_after_processing | boolean | 上传时选择“处理完成后自动发布”时为 true；基础处理未完成、发布条件不满足或图片归档后会保持/清除为 false |
| width / height | int nullable | 尺寸 |
| mime_type | varchar nullable | MIME |
| file_size | bigint nullable | 文件大小 |
| original_key | varchar nullable | 原图存储 Key，指向当前启用存储驱动中的文件 |
| display_key | varchar nullable | 展示图存储 Key，指向当前启用存储驱动中的文件 |
| thumbnail_key | varchar nullable | 缩略图存储 Key，指向当前启用存储驱动中的文件 |
| uploaded_by | fk users nullable | 上传者；手工补录或测试数据可为空 |
| photo_upload_batch_id | fk photo_upload_batches nullable | 上传批次；单张手工补录或历史数据可为空 |
| published_at | datetime nullable | 发布时间 |
| created_at / updated_at | timestamps | 时间戳 |

P1-15 高级搜索与资料增强基于 photos、photo_category、photo_tag 和互动统计做数据库内组合筛选。清晰度和横竖图由 width / height 派生，不新增独立字段；人物关系和对手均使用普通标签表达；来源存在性基于 photos.source_url；水印状态使用 watermark_status 维护。搜索运营切片已新增 search_recommendations / search_queries，只用于推荐词配置、热门词聚合和排障，不接外部搜索引擎。

### photo_upload_batches

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 上传批次 ID |
| mode | enum | `standalone` 单独上传、`album` 相册内上传 |
| album_id | fk albums nullable | 目标相册；相册内上传时必填 |
| uploaded_by | fk users nullable | 上传者；系统补录或测试数据可为空 |
| status | enum | `draft`、`processing`、`completed`、`failed`、`partially_failed` |
| total_count | int | 总文件数 |
| success_count | int | 成功文件数 |
| failed_count | int | 失败文件数 |
| note | text nullable | 批次备注 |
| created_at / updated_at | timestamps | 时间戳 |

上传批次用于支撑后台 `图片批量上传` 和 `上传批次 / 批量整理` 页面。批量上传支持选择草稿或“处理完成后自动发布”，并可选择自动创建 OCR / 智能标签任务；批次仍只记录一次批量上传和整理过程，不改变图片本身是否可以加入多个相册的规则。

### albums

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 相册 ID |
| title | varchar | 相册名 |
| slug | varchar unique | URL 标识 |
| description | text nullable | 描述 |
| cover_photo_id | fk photos nullable | 手动选择的封面图；只能选择相册内已发布图片；为空时默认随机使用相册内一张已发布图片作为封面 |
| sort_order | int | 排序 |
| status | enum | `draft` 草稿、`published` 已发布、`hidden` 隐藏 |
| published_at | datetime nullable | 发布时间 |
| created_at / updated_at | timestamps | 时间戳 |

### album_category

| 字段 | 类型 | 说明 |
|---|---|---|
| album_id | fk albums | 相册 |
| category_id | fk categories | 相册默认分类；每个主分类选择一个子分类 |

### album_photo

| 字段 | 类型 | 说明 |
|---|---|---|
| album_id | fk albums | 相册 |
| photo_id | fk photos | 图片 |

### categories

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 分类 ID |
| parent_id | fk categories nullable | 父分类；为空表示主分类，不为空表示子分类 |
| name | varchar | 分类名 |
| slug | varchar unique | URL 标识，Phase 1 先全局唯一，避免不同主分类下重名 slug 带来路由歧义 |
| description | text nullable | 描述 |
| cover_photo_id | fk photos nullable | 分类封面图；等 photos 表落地后再补外键 |
| sort_order | int | 排序 |
| visibility | enum | public、hidden |
| required_for_publish | boolean | 是否属于发布前必须选择的主分类；当前为生涯阶段、年份、场景 |
| created_at / updated_at | timestamps | 时间戳 |

### photo_category

| 字段 | 类型 | 说明 |
|---|---|---|
| photo_id | fk photos | 图片 |
| category_id | fk categories | 图片分类；相册内图片来自相册同步，单独图片来自上传时选择 |

### tags

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 标签 ID |
| name | varchar unique | 标签名 |
| description | text nullable | 说明 |
| sort_order | int | 排序 |
| created_at / updated_at | timestamps | 时间戳 |

### photo_tag

| 字段 | 类型 | 说明 |
|---|---|---|
| photo_id | fk photos | 图片 |
| tag_id | fk tags | 标签 |

### search_recommendations

P1 搜索运营切片已落库。该表用于后台维护推荐搜索词，服务 `/search` 空状态 / 无结果状态和公共导航搜索建议；不承载广告、赞助关键词、付费排序或个性化推荐。

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 推荐词 ID |
| keyword | varchar | 实际搜索词 |
| title | varchar | 前台展示标题 |
| description | text nullable | 前台说明，可为空 |
| url | varchar nullable | 跳转 URL；为空时默认 `/search?q=keyword` |
| is_active | boolean | 是否启用；前台只展示启用项 |
| sort_order | int | 排序 |
| internal_note | text nullable | 内部备注；只在后台可见，不进入前台 payload |
| created_at / updated_at | timestamps | 时间戳 |

### search_queries

P1 搜索运营切片已落库。该表只记录用户主动提交的有效搜索，用于热门词聚合和排障；不记录 IP、UA，不向前台暴露原始日志，不提供用户搜索历史页。

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 搜索记录 ID |
| user_id | fk users nullable | 登录用户可关联；游客为空，不建立个人画像 |
| keyword | varchar | 原始搜索词，截断到 120 字符 |
| normalized_keyword | varchar index | 规范化搜索词，用于聚合 |
| source | varchar index | 来源入口，例如 `search_page` |
| result_count | int index | 本次公开搜索结果数 |
| filters_json | json nullable | 基础筛选摘要；不包含关键词、IP、UA 或内部字段 |
| created_at / updated_at | timestamps | 时间戳 |

热门搜索公开边界：只聚合有效、有结果且达到最小次数阈值的搜索词；明显空白、过短、纯符号和超长关键词不进入热门词。搜索建议接口只输出 `term`、`label`、`description`、`url`、`source` 安全字段。

Phase 1 不在 photos 表中直接写入 team_id、competition_id、match_id 等外键。球队、赛事、赛季、年份、比赛等通过 8 个默认主分类及其子分类承载；人物同框可通过人物关系主分类及相关标签共同承载。

## 3. 足球资料维度说明

Phase 1 不建立独立的球队、赛事、赛季或比赛资料表。相关信息按以下方式进入图库归档体系：

- 球队、赛事、赛季、年份、比赛等作为 8 个默认主分类下的子分类维护。
- 人物同框和对手名称统一作为普通标签维护。
- 事件或比赛主题的一组图片通过相册表达，例如“2022-12-18 世界杯决赛 阿根廷 vs 法国”。
- 后台不单列球队管理、赛事管理、比赛管理或人物管理入口。

## 4. 来源与分析

### 图片来源
图片来源不再单独建表，直接保存在 photos.source_url。来源平台通过 8 个默认主分类中的 来源平台 子分类维护；source_url 可为空，填写后前台详情页展示为可点击链接。

### photo_analysis_results

P1-11 已落库。当前真实写入基础文件信息、SHA-256 精确哈希、可读取的 EXIF 和处理时间；相似图切片新增后台人工触发的感知哈希和候选关系记录；OCR 和智能标签切片新增后台人工触发的识别结果。COS 临时读写真实验证已通过，当前 6 张 COS 业务原图已完成展示图/缩略图、智能标签和相似候选真实验证，图片质量评分不纳入项目。

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| photo_id | fk photos unique | 图片；一张图片对应一条分析结果 |
| width | unsigned int nullable | 宽度 |
| height | unsigned int nullable | 高度 |
| mime_type | varchar nullable | MIME 类型 |
| file_size | unsigned bigint nullable | 文件大小 |
| sha256_hash | varchar(64) nullable index | 文件 SHA-256；精确重复提示基于该字段 |
| perceptual_hash | varchar(16) nullable index | 64 位 dHash 感知特征；仅在后台手动触发相似候选计算时写入 |
| exif_json | json nullable | 可读取的 EXIF 基础数据 |
| ocr_text | longtext nullable | 后台手动 OCR 识别文本；不直接作为前台搜索字段 |
| ci_labels_json | json nullable | 后台手动智能标签结果；COS 临时读写已真实验证，数据万象真实调用仍需业务原图 |
| ci_quality_json | json nullable | 历史兼容字段；图片质量评分不纳入项目，不读取、不写入 |
| error_message | text nullable | 分析错误 |
| processed_at | datetime nullable | 处理时间 |

### photo_similarity_candidates

相似图首个切片不创建自动相似组，只保存后台人工审核用的候选关系。图片顺序按 ID 规范化，避免同一对图片重复记录；人工结论不会自动修改图片记录或发布状态。

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 候选关系 ID |
| photo_id | fk photos | 候选图片 A |
| candidate_photo_id | fk photos | 候选图片 B |
| distance | unsigned tinyint | dHash 汉明距离，当前阈值不超过 12 |
| similarity_score | decimal(5,2) | 根据 64 位特征换算的辅助相似度百分比 |
| status | varchar index | pending_review、kept_separate、confirmed_duplicate、ignored |
| reviewed_by | fk users nullable | 人工处理人 |
| reviewed_at | timestamp nullable | 人工处理时间 |
| created_at / updated_at | timestamps | 时间戳 |

## 5. 系统设置

### settings

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 设置 ID |
| group | varchar | 配置分组，例如 `site`、`home`、`footer` |
| key | varchar | 配置键，例如 `hero_slides`、`latest_photos`、`topic_module` |
| value | json nullable | 配置值，保存结构化 JSON |
| description | varchar nullable | 后台说明 |
| updated_by | fk users nullable | 最近修改人 |
| created_at / updated_at | timestamps | 时间戳 |

`settings` 是 Phase 1 P0 首页配置的数据基础，用于保存站点基础信息、导航、头图轮播、分类模块、最新照片模块、专题模块、页脚配置，以及项目后台的图片存储配置。P0 不做拖拽式 CMS，不在数据库中为首页每个小模块拆独立表；首页固定结构通过结构化 JSON 配置驱动，前台读取时必须过滤草稿、隐藏、归档、版权受限或请求下架内容。存储配置使用 group=storage、key=config 保存，腾讯云 SecretKey 必须加密后写入 JSON，表单不得回显明文。
## 6. 轻社交

### comments

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 评论 ID |
| user_id | fk users | 提交用户；游客不能提交 |
| photo_id | fk photos | 所属图片；只允许对公开图片提交 |
| parent_id | fk comments nullable | 回复关系预留；P1-5 不做楼中楼 |
| content | text | 评论内容或补充说明；前台保留换行但不渲染 HTML |
| status | enum | pending、published、rejected、hidden、deleted；新提交默认 pending |
| correction_field | varchar nullable | 纠错建议字段，如标题、日期、赛事、球队、人物同框、来源、版权备注、分类、标签或其他 |
| suggested_value | text nullable | 用户建议修正或补充的内容 |
| evidence_url | varchar nullable | 用户提供的证据链接 |
| meta | json nullable | 后续审核、处理记录或扩展信息预留 |
| like_count | int | 评论点赞数预留；P1-5 不做评论点赞 |
| reviewed_by | fk users nullable | 审核人 |
| reviewed_at | timestamp nullable | 审核时间 |
| moderation_note | text nullable | 后台审核备注 |
| risk_level | varchar | clean、low、medium、high 风险等级 |
| sensitive_word_hits | json nullable | 敏感词命中结果 |
| created_at / updated_at | timestamps | 时间戳 |

P1-5 已落地 comments 表基础能力：图片详情页只公开展示 published 状态的 discussion 评论；普通评论和图片信息补充/纠错提交后默认进入 pending；correction 不进入公开评论流，由 P1-6 审核队列处理。

P1-6 已补齐评论审核字段：reviewed_by 记录审核人，reviewed_at 记录审核时间，moderation_note 记录后台审核备注，risk_level 记录 clean、low、medium、high 风险等级，sensitive_word_hits 记录命中的敏感词规则；meta 继续作为审核历史等扩展信息。

### photo_favorites

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| photo_id | fk photos | 图片；只允许对公开图片创建收藏 |
| user_id | fk users | 用户 |
| created_at / updated_at | timestamps | 时间戳 |

同一用户对同一图片只保留一条收藏记录；P1-4 不做收藏夹字段、不公开收藏数，`我的收藏` 和收藏夹后续在用户中心切片确认。

### photo_likes

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| photo_id | fk photos | 图片；只允许对公开图片创建点赞 |
| user_id | fk users | 用户 |
| created_at / updated_at | timestamps | 时间戳 |

同一用户对同一图片只保留一条点赞记录；P1-4 在图片详情页公开点赞数和当前用户点赞状态。

### photo_shares

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| photo_id | fk photos | 图片；只允许对公开图片记录分享 |
| user_id | fk users nullable | 用户；游客分享不绑定用户 |
| channel | varchar | 分享渠道；P1-14 支持 `copy_link`、`native_share`、`weibo`、`wechat_qr` |
| page_url | varchar nullable | 分享时的图片详情页 URL |
| ip_address | varchar nullable | 基础安全排查线索 |
| user_agent | varchar nullable | 基础安全排查线索 |
| created_at / updated_at | timestamps | 时间戳 |

P1-14 已在 P1-4 基础上补齐分享弹层、微博跳转分享、微信二维码引导和四类分享渠道记录；仍不接微信 JS-SDK / 真实第三方分享 API，不生成分享海报，不生成短链接，不做分享返利。真实 API、海报、短链接和复杂治理后续独立确认；分享返利默认不做。
P1-8 互动统计与排行榜不新增统计表，运行时从 `photo_likes`、`photo_favorites`、`photo_shares` 和 `comments` 聚合。公开排行榜只统计 `published` 图片，并排除 `restricted`、`remove_requested`；评论数只计算 `type = discussion` 且 `status = published` 的普通评论，不计待审核评论、纠错投稿、举报或后台备注。综合热度公式固定为：收藏 x4 + 点赞 x3 + 评论 x2 + 分享 x1。P1-8 不做浏览量、趋势图、人工排行榜管理、异常刷赞处理或第三方平台统计。

### reports

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| user_id | fk users | 举报人 |
| target_type | varchar | 目标类型；P1-6 固定为 comment |
| target_id | bigint | 目标 ID |
| reason | varchar | 原因 |
| details | text nullable | 举报补充说明 |
| status | enum | pending、resolved、rejected、closed |
| handled_by | fk users nullable | 处理人 |
| handled_at | timestamp nullable | 处理时间 |
| internal_note | text nullable | 后台处理备注 |
| risk_level | varchar | 风险等级 |
| sensitive_word_hits | json nullable | 敏感词命中结果 |
| created_at / updated_at | timestamps | 时间戳 |

P1-6 只支持评论举报目标；处理举报可以同步隐藏违规评论，但不物理删除评论或举报记录。

### sensitive_words

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| word | varchar unique | 敏感词词条 |
| severity | varchar | low、medium、high |
| is_enabled | boolean | 是否启用 |
| internal_note | text nullable | 后台备注 |
| created_at / updated_at | timestamps | 时间戳 |

P1-6 敏感词只做简单包含匹配和风险标记，不自动拒绝、不自动封禁、不对前台暴露具体词库。
### notifications

| 字段 | 类型 | 说明 |
|---|---|---|
| id | uuid pk | 通知 ID，复用 Laravel database notifications 默认结构 |
| notifiable_type | varchar | 通知接收模型类型；P1-7 固定为用户 |
| notifiable_id | bigint | 通知接收用户 ID |
| data | json | 通知安全摘要，包含 category、title、message、url、target_type、target_id |
| read_at | timestamp nullable | 已读时间 |
| created_at / updated_at | timestamps | 时间戳 |

P1-7 已落地 notifications 表：评论 / 纠错审核结果、举报处理结果、账号封禁 / 解封提示和系统通知写入站内通知；P1-10 补充勋章成就通知 `badge`；前台只展示当前用户自己的通知安全摘要，不暴露后台审核备注、敏感词命中或内部处理记录。
## 7. 勋章与成就

P1-10 已落地勋章 / 成就基础闭环：只做基础规则、人工发放/撤销、用户佩戴和站内通知；不做积分商城、复杂任务系统、签到、公开勋章大厅、勋章排行榜、纪念日自动发放或运营活动配置。

### badges

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 勋章 ID |
| name | varchar | 勋章名称 |
| slug | varchar unique | 勋章标识 |
| description | text nullable | 前台说明 |
| icon_key | varchar nullable | 前端图标标识 |
| color | varchar | 前台展示颜色 |
| rule_type | varchar | `manual`、`registered`、`favorites_count`、`comments_count`、`supporter` |
| rule_threshold | int nullable | 规则阈值，例如收藏数、评论数 |
| is_active | boolean | 是否启用 |
| sort_order | int | 排序 |
| internal_note | text nullable | 后台内部备注 |
| created_at / updated_at | timestamps | 时间戳 |

### user_badges

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 用户勋章记录 ID |
| user_id | fk users | 用户 |
| badge_id | fk badges | 勋章 |
| source | varchar | `rule` 规则获得、`manual` 后台发放 |
| status | varchar | `earned` 已获得、`revoked` 已撤销 |
| equipped | boolean | 是否当前佩戴 |
| awarded_by | fk users nullable | 后台发放或撤销操作人 |
| awarded_at | timestamp nullable | 获得时间 |
| revoked_at | timestamp nullable | 撤销时间 |
| note | text nullable | 备注 |
| created_at / updated_at | timestamps | 时间戳 |

同一用户对同一勋章只保留一条记录；撤销后保留历史状态，不物理删除。P1-10 获得规则只读取用户注册、收藏数量、已发布普通评论数量和支持者身份，不读取待审核评论、纠错投稿、举报、后台备注或支付敏感信息。
## 8. 赞助与支付

P1-9 已落地赞助支持基础闭环：只做模拟支付和后台手动处理，不接真实微信 / 支付宝 API，不做真实回调验签、退款 API、发票、续费提醒、优惠码、自定义金额、付费内容墙或图片下载权益。本站赞助支持不是图片版权售卖。

### sponsorship_plans

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 方案 ID |
| name | varchar | 方案名称 |
| slug | varchar unique | 方案标识 |
| amount_cents | int | 金额，单位分 |
| duration_days | int nullable | 支持者身份有效天数；为空表示一次性支持 |
| badge_level | varchar | 支持者徽章等级 |
| benefits | text nullable | 前台展示说明，每行一条 |
| is_active | boolean | 是否启用 |
| sort_order | int | 排序 |
| internal_note | text nullable | 后台内部备注 |
| created_at / updated_at | timestamps | 时间戳 |

### sponsorship_orders

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 订单 ID |
| order_no | varchar unique | 商户订单号 |
| user_id | fk users | 用户 |
| sponsorship_plan_id | fk sponsorship_plans nullable | 赞助方案，方案删除后保留订单 |
| amount_cents | int | 金额，单位分 |
| channel | varchar | P1-9 使用 `mock`、`manual`；真实 `wechat`、`alipay` 后续单独接入 |
| status | varchar | `pending`、`paid`、`failed`、`closed`、`refunded` |
| transaction_id | varchar nullable | 第三方交易号 |
| paid_at | datetime nullable | 支付时间 |
| closed_at | datetime nullable | 关闭时间 |
| refunded_at | datetime nullable | 退款标记时间 |
| raw_callback_json | json nullable | 模拟支付或后续回调原文 |
| admin_note | text nullable | 后台内部备注 |
| created_at / updated_at | timestamps | 时间戳 |

### payment_logs

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 日志 ID |
| sponsorship_order_id | fk sponsorship_orders nullable | 关联订单 |
| channel | varchar | `mock`、`manual`，真实渠道后续扩展 |
| event_type | varchar | 事件类型，如 `order_created`、`payment_success`、`order_closed`、`order_refunded` |
| status | varchar | `received`、`processed`、`failed` |
| payload | json nullable | 事件载荷 |
| message | text nullable | 日志说明 |
| created_at / updated_at | timestamps | 时间戳 |

## 9. 系统任务

### processing_jobs

P1-11 已落库。当前任务类型包括 metadata、hash、ocr_placeholder、datawanxiang_derivatives、similarity 和 ocr；智能标签任务已停用，历史智能标签结果仅作为兼容数据保留，不再读取或写入。失败任务可在后台异步重新入队或批量重试，上传任务通过图片关系汇总待处理、处理中和失败任务数量；图片质量评分不纳入项目。

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| photo_id | fk photos nullable | 关联图片 |
| status | varchar index | `pending`、`running`、`done`、`failed` |
| attempts | unsigned int | 尝试次数 |
| payload | json nullable | 任务参数；P1-11 默认不需要填写 |
| error_message | text nullable | 错误信息 |
| processed_at | datetime nullable | 处理完成时间 |
| created_at / updated_at | timestamps | 时间戳 |

## 10. 索引建议

- `photos(status, event_date)`
- `albums(slug)`
- `album_category(album_id, category_id)` 唯一索引
- `album_category(category_id, album_id)`
- album_photo(album_id, photo_id) 唯一索引
- album_photo(photo_id, album_id)
- `photo_category(photo_id, category_id)` 唯一索引
- `photo_category(category_id, photo_id)`
- photos(event_date)
- `categories(parent_id, slug)` 唯一索引
- `categories(parent_id, sort_order)`
- `tags(name)`
- `settings(group, key)` 唯一索引
- `comments(photo_id, type, status, created_at)`
- `comments(user_id, type, status)`
- `photo_favorites(photo_id, user_id)` 唯一索引
- `photo_favorites(user_id, created_at)`
- `photo_likes(photo_id, user_id)` 唯一索引
- `photo_likes(user_id, created_at)`
- `notifications(notifiable_type, notifiable_id)`
- `badges(slug)` 唯一索引
- `badges(is_active, sort_order)`
- `user_badges(user_id, badge_id)` 唯一索引
- `user_badges(user_id, equipped)`
- `user_badges(user_id, status)`
- `photo_shares(photo_id, created_at)`
- `photo_shares(user_id, created_at)`
- `photo_shares(channel)`
- `sponsorship_plans(slug)` 唯一索引
- `sponsorship_plans(is_active, sort_order)`
- `sponsorship_orders(order_no)` 唯一索引
- `sponsorship_orders(user_id, created_at)`
- `sponsorship_orders(status, created_at)`
- `payment_logs(sponsorship_order_id)`
- `payment_logs(channel)`
- `supporter_profiles(user_id)` 唯一索引
- `supporter_profiles(show_publicly, last_supported_at)`
