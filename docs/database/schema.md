# 数据库设计文档

数据库以 MySQL 8.0 为默认实现。字段类型可在 Laravel migration 中按实际需要调整。本设计是开发前逻辑模型，不是最终 migration 代码。

## 开发阶段说明

本文档记录项目的长期逻辑模型，不代表所有表都会在第一阶段同时开发。

Phase 1 只落图库核心表，用于完成图片录入、管理、浏览和基础检索闭环：

- `photos`
- `albums`
- `categories`
- `album_category`
- `album_photo`
- `photo_category`
- `tags`
- `photo_tag`
- `teams`
- `competitions`
- `matches`
- `sources`

Phase 1 暂不创建以下后续功能表：

- `supporter_profiles`
- `photo_analysis_results`
- `similar_groups`
- `comments`
- `likes`
- `favorites`
- `reports`
- `sponsorship_orders`
- `processing_jobs`

`users` 表使用 Laravel/Fortify 当前已生成的基础结构。角色、手机号、支持者有效期、用户状态等字段会在权限或会员阶段再扩展。

## Phase 1 相册、分类与标签模型决策

Phase 1 采用“相册/专题 + 7 个必选主分类 + 子分类 + 多标签”模型。

- 主分类：`categories.parent_id = null`，Phase 1 固定为 `生涯阶段`、`赛事`、`赛季`、`年份`、`场景`、`图片类型`、`来源平台`。
- 主分类固定：Phase 1 不允许后台随意新增第 8 个主分类；后续如果确实需要新增主分类，必须先更新产品文档、数据库约束和旧数据补齐方案。
- 子分类：`categories.parent_id = 主分类 ID`，例如 `阿根廷国家队`、`世界杯`、`2022年`、`球场内`、`社媒图`。
- 子分类管理：后台允许在 7 个固定主分类下自由新增、编辑和排序子分类。
- 相册/专题：用于批量组织一组图片，例如 `2022世界杯决赛`、`2022世界杯夺冠之路`。
- 相册默认分类：新建相册时，必须从 7 个主分类中分别选择一个子分类，7 个主分类缺一不可，通过 `album_category` 实现。
- 图片相册关系：图片可以加入一个或多个相册，通过 `album_photo` 实现。
- 相册内图片分类：图片加入相册时，完全同步该相册已选择的 7 个分类，并写入 `photo_category`；相册内图片不允许额外手动补充分类。
- 单独上传图片分类：图片不属于相册时，必须从 7 个主分类中分别选择一个子分类，7 个主分类缺一不可，并写入 `photo_category`。
- 分类兜底：每个主分类必须预置 `待补充` 子分类，用于资料暂时不完整、需要后续考古补充的图片；信息不知道时也必须选择对应主分类下的 `待补充`，不能留空。
- 分类修正：若后续用户在评论或其他反馈中补充了可靠信息，管理员或编辑可以将 `待补充` 修改为已有子分类，或先新增子分类再重新选择。
- 标签：用于描述更细粒度的信息，例如 `进球`、`庆祝`、`高清`、`捧杯`，通过 `photo_tag` 实现；图片标签可以为空。
- 标签管理：后台允许自由新增、编辑和排序标签，但每个标签必须选择一个标签类型，避免标签长期混乱。
- 标签类型：Phase 1 预置 `动作`、`情绪`、`画质`、`人物关系`、`荣誉`、`画面内容`、`服装/装备`、`地点`。

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

`photos.sha256`、`photos.perceptual_hash`、`photos.similar_group_id` 在 Phase 1 中只作为后续能力预留字段，不作为上传拦截条件。

## Phase 1 批量上传与整理规则

Phase 1 必须支持“批量上传到相册”。

推荐后台流程：

1. 新建相册/专题。
2. 为相册选择 7 个必选主分类下的子分类。
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
- 单独上传且不属于任何相册的图片，只要状态为 `published` 且 7 个主分类已选满，也应在前台图片列表、分类检索和首页最新照片中展示。

图片文件命名与标题规则：

- 图片上传后由系统自动重命名文件，不使用用户上传时的原始文件名作为存储文件名。
- 重命名格式为 `YYYYMMDD-HHmmss-6位随机码.原扩展名`，例如 `20260825-143522-A7K9Q2.jpg`。
- 数据库保留原始文件名，用于后续追溯线索。
- 图片标题默认使用系统重命名后的文件名，因此正常上传后不会出现空标题。
- 管理员或编辑发布前可以修改标题，也可以不修改；如果人为清空标题，后台不允许保存。
- 系统生成的存储文件名不提供后台手动修改入口。

相册封面规则：

- 管理员或编辑可以为相册手动选择封面图。
- 手动封面只能选择该相册内的 `published` 已发布图片。
- 如果没有手动选择封面图，系统默认从相册内 `published` 已发布图片中随机选择一张作为封面展示。
- 随机封面只用于展示兜底，不写死为手动封面；后续相册图片变化时可以重新随机展示。

相册发布状态规则：

- 新建相册默认进入 `draft` 草稿状态，用于先上传和整理图片。
- 只有 `published` 已发布相册在前台公开展示。
- 相册发布前必须至少包含 1 张 `published` 已发布图片；否则不允许发布，并提示管理员或编辑先上传并发布图片。
- 已发布相册的前台页面只展示 `published` 已发布图片，仍处于 `draft` 草稿/待整理状态的图片不展示。
- 已发布相册必须始终至少保留 1 张 `published` 已发布图片；如果把图片改为草稿、归档、删除或移出相册会导致该相册没有已发布图片，后台必须阻止操作，并提示先将相册改为 `draft` 草稿或 `hidden` 隐藏。
- `hidden` 隐藏状态用于临时下架或不希望前台展示的相册。
- 相册发布时写入 `published_at`。

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

## 2. 图库核心

### photos

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 图片 ID |
| uuid | char(36) unique | 外部引用 ID |
| title | varchar | 图片标题，不允许为空；默认使用系统重命名后的文件名 |
| description | text nullable | 图片说明，可为空；用于补充背景、考古线索、来源备注等长文本 |
| original_filename | varchar nullable | 用户上传时的原始文件名，用于追溯 |
| stored_filename | varchar | 系统重命名后的文件名，格式为 `YYYYMMDD-HHmmss-6位随机码.原扩展名` |
| taken_at | datetime nullable | 拍摄时间 |
| event_date | date nullable | 事件日期 |
| career_stage | varchar nullable | 生涯阶段 |
| team_id | fk teams nullable | 球队 |
| competition_id | fk competitions nullable | 赛事 |
| match_id | fk matches nullable | 比赛 |
| source_id | fk sources nullable | 来源 |
| copyright_status | enum | `unknown`、`public_reference`、`official_public`、`user_submitted`、`restricted`、`takedown` |
| review_status | enum | `draft` 草稿/待整理、`published` 已发布、`archived` 已归档 |
| analysis_status | enum | `pending`、`processing`、`done`、`failed` |
| width / height | int nullable | 尺寸 |
| mime_type | varchar | MIME |
| file_size | bigint | 文件大小 |
| original_key | varchar | COS 原图 Key |
| display_key | varchar nullable | COS 展示图 Key |
| thumbnail_key | varchar nullable | COS 缩略图 Key |
| sha256 | char(64) nullable index | 文件哈希，Phase 1 仅预留，不用于上传拦截 |
| perceptual_hash | varchar nullable index | 感知哈希，后续相似图管理预留 |
| similar_group_id | fk similar_groups nullable | 相似图组，后续后台治理预留 |
| quality_score | decimal nullable | 质量评分 |
| is_featured | boolean | 是否推荐 |
| uploaded_by | fk users | 上传者 |
| published_at | datetime nullable | 发布时间 |
| created_at / updated_at | timestamps | 时间戳 |

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
| slug | varchar | URL 标识，同一父分类下唯一 |
| description | text nullable | 描述 |
| cover_photo_id | fk photos nullable | 分类封面图 |
| sort_order | int | 排序 |
| visibility | enum | `public`、`hidden` |

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
| type | enum | `动作`、`情绪`、`画质`、`人物关系`、`荣誉`、`画面内容`、`服装/装备`、`地点` |
| description | text nullable | 说明 |
| sort_order | int | 排序 |
| created_at / updated_at | timestamps | 时间戳 |

### photo_tag

| 字段 | 类型 | 说明 |
|---|---|---|
| photo_id | fk photos | 图片 |
| tag_id | fk tags | 标签 |

## 3. 足球资料

### teams

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 球队 ID |
| name | varchar | 中文名 |
| name_en | varchar nullable | 英文名 |
| type | enum | `club`、`national` |
| country | varchar nullable | 国家 |

### competitions

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 赛事 ID |
| name | varchar | 赛事名 |
| type | enum | `league`、`cup`、`international`、`award`、`friendly` |

### matches

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 比赛 ID |
| competition_id | fk competitions | 赛事 |
| home_team_id | fk teams nullable | 主队 |
| away_team_id | fk teams nullable | 客队 |
| match_date | date | 比赛日期 |
| title | varchar | 展示标题 |
| score | varchar nullable | 比分 |
| venue | varchar nullable | 场馆 |
| season | varchar nullable | 赛季 |

## 4. 来源与分析

### sources

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 来源 ID |
| name | varchar | 来源名 |
| url | varchar nullable | 原始链接 |
| type | enum | `media`、`official`、`social`、`user`、`unknown` |
| note | text nullable | 备注 |

### photo_analysis_results

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| photo_id | fk photos | 图片 |
| exif_json | json nullable | EXIF |
| ocr_text | longtext nullable | OCR 文本 |
| ci_labels_json | json nullable | 数据万象标签 |
| ci_quality_json | json nullable | 质量评估 |
| error_message | text nullable | 错误 |
| processed_at | datetime nullable | 处理时间 |

### similar_groups

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 相似组 ID |
| representative_photo_id | fk photos nullable | 代表图 |
| note | text nullable | 说明 |

## 5. 轻社交

### comments

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 评论 ID |
| user_id | fk users | 用户 |
| photo_id | fk photos nullable | 图片 |
| category_id | fk categories nullable | 分类 |
| parent_id | fk comments nullable | 回复 |
| content | text | 内容 |
| status | enum | `pending`、`published`、`rejected`、`deleted` |
| like_count | int | 点赞数 |

### likes

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| user_id | fk users | 用户 |
| target_type | varchar | `photo`、`album`、`comment` |
| target_id | bigint | 目标 ID |

### favorites

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| user_id | fk users | 用户 |
| photo_id | fk photos | 图片 |
| collection_name | varchar nullable | 收藏夹 |

### reports

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| user_id | fk users | 举报人 |
| target_type | varchar | 目标类型 |
| target_id | bigint | 目标 ID |
| reason | varchar | 原因 |
| status | enum | `pending`、`resolved`、`rejected` |

## 6. 赞助与支付

### sponsorship_orders

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 订单 ID |
| order_no | varchar unique | 商户订单号 |
| user_id | fk users | 用户 |
| amount_cents | int | 金额，单位分 |
| channel | enum | `wechat`、`alipay`、`manual` |
| status | enum | `pending`、`paid`、`closed`、`refunded` |
| transaction_id | varchar nullable | 第三方交易号 |
| paid_at | datetime nullable | 支付时间 |
| raw_callback_json | json nullable | 回调原文 |

## 7. 系统任务

### processing_jobs

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | ID |
| type | varchar | `exif`、`ocr`、`thumbnail`、`similar_search` |
| photo_id | fk photos nullable | 图片 |
| status | enum | `pending`、`running`、`done`、`failed` |
| attempts | int | 尝试次数 |
| error_message | text nullable | 错误 |

## 8. 索引建议

- `photos(review_status, event_date)`
- `albums(slug)`
- `album_category(album_id, category_id)` 唯一索引
- `album_category(category_id, album_id)`
- `album_photo(album_id, photo_id)` 唯一索引
- `album_photo(photo_id, album_id)`
- `photo_category(photo_id, category_id)` 唯一索引
- `photo_category(category_id, photo_id)`
- `photos(team_id, competition_id, event_date)`
- `photos(sha256)`
- `categories(parent_id, slug)` 唯一索引
- `categories(parent_id, sort_order)`
- `tags(name)`
- `comments(photo_id, status, created_at)`
- `likes(user_id, target_type, target_id)` 唯一索引
- `favorites(user_id, photo_id)` 唯一索引
- `sponsorship_orders(order_no)` 唯一索引
