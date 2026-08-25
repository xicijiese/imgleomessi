# 数据库设计文档

数据库以 MySQL 8.0 为默认实现。字段类型可在 Laravel migration 中按实际需要调整。本设计是开发前逻辑模型，不是最终 migration 代码。

## 开发阶段说明

本文档记录项目的长期逻辑模型，不代表所有表都会在第一阶段同时开发。

Phase 1 只落图库核心表，用于完成图片录入、管理、浏览和基础检索闭环：

- `photos`
- `albums`
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
| title | varchar | 图片标题 |
| description | text nullable | 图片说明 |
| album_id | fk albums nullable | 所属主相册 |
| taken_at | datetime nullable | 拍摄时间 |
| event_date | date nullable | 事件日期 |
| career_stage | varchar nullable | 生涯阶段 |
| team_id | fk teams nullable | 球队 |
| competition_id | fk competitions nullable | 赛事 |
| match_id | fk matches nullable | 比赛 |
| source_id | fk sources nullable | 来源 |
| copyright_status | enum | `unknown`、`public_reference`、`official_public`、`user_submitted`、`restricted`、`takedown` |
| review_status | enum | `draft`、`pending`、`published`、`rejected`、`archived` |
| analysis_status | enum | `pending`、`processing`、`done`、`failed` |
| width / height | int nullable | 尺寸 |
| mime_type | varchar | MIME |
| file_size | bigint | 文件大小 |
| original_key | varchar | COS 原图 Key |
| display_key | varchar nullable | COS 展示图 Key |
| thumbnail_key | varchar nullable | COS 缩略图 Key |
| sha256 | char(64) nullable index | 完全去重 |
| perceptual_hash | varchar nullable index | 自建感知哈希，可选 |
| similar_group_id | fk similar_groups nullable | 相似图组 |
| quality_score | decimal nullable | 质量评分 |
| is_featured | boolean | 是否推荐 |
| uploaded_by | fk users | 上传者 |
| published_at | datetime nullable | 发布时间 |
| created_at / updated_at | timestamps | 时间戳 |

### albums

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 相册 ID |
| parent_id | fk albums nullable | 父相册 |
| type | enum | `event`、`topic`、`career_stage`、`collection` |
| title | varchar | 相册名 |
| slug | varchar unique | URL 标识 |
| description | text nullable | 描述 |
| event_date | date nullable | 事件日期 |
| cover_photo_id | fk photos nullable | 封面图 |
| sort_order | int | 排序 |
| visibility | enum | `public`、`hidden` |

### tags

| 字段 | 类型 | 说明 |
|---|---|---|
| id | bigint pk | 标签 ID |
| name | varchar unique | 标签名 |
| type | enum | `action`、`scene`、`person`、`quality`、`topic`、`source` |
| description | text nullable | 说明 |

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
| album_id | fk albums nullable | 相册 |
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
- `photos(album_id, review_status)`
- `photos(team_id, competition_id, event_date)`
- `photos(sha256)`
- `albums(slug)`
- `tags(name)`
- `comments(photo_id, status, created_at)`
- `likes(user_id, target_type, target_id)` 唯一索引
- `favorites(user_id, photo_id)` 唯一索引
- `sponsorship_orders(order_no)` 唯一索引
