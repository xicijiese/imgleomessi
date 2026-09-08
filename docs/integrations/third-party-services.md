# 第三方服务对接方案

本文件描述“我们怎么用”第三方服务。所有密钥不得写入代码、文档或 Git；腾讯云 COS / 数据万象凭证可由项目管理后台加密保存，服务器只在后端和队列中读取。

## 1. 腾讯云 COS

### 我们怎么用

- 存储原图、展示图、缩略图。
- 图片上传由 Laravel 后端控制，后台编辑员上传后写入当前启用的存储驱动；生产环境优先写入 COS，本地开发或过渡阶段可使用本地存储。
- 前台图片访问走 COS/CDN 域名；项目后台可在系统设置的存储与处理区域切换本地存储或腾讯云 COS。
- 原图默认不直接公开；前台优先使用展示图和缩略图。
- 文件 Key 使用结构化路径：

```text
photos/originals/{yyyy}/{mm}/{stored_filename}
photos/derived/{photo_uuid}/display.webp
photos/derived/{photo_uuid}/thumbnail.webp
```

### Laravel 接入

Laravel 使用文件系统抽象管理图片存储，并通过 `league/flysystem-aws-s3-v3` 的 S3 兼容适配器接入 COS。项目后台系统设置负责本地/COS 切换、凭证加密保存和存储读写检测；图片上传使用当前启用方式，未迁移的本地历史图片继续从本地读取。数据万象云上图片处理使用腾讯云官方 qcloud/cos-sdk-v5 PHP SDK，不把 SDK 凭证写入代码。

官方文档：

- Laravel File Storage：https://laravel.com/framework/docs/filesystem
- 腾讯云 COS 文档：https://cloud.tencent.com/document/product/436
- 腾讯云 PHP SDK：https://cloud.tencent.cn/document/sdk/PHP

## 2. 腾讯云数据万象

### 我们怎么用

数据万象只在后端队列中调用，不在前台用户请求中同步调用。当前项目已完成 COS + 数据万象开关、展示图/缩略图派生处理、相似图后台辅助处理、OCR 和智能标签处理适配。OCR 使用官方 PHP SDK 的 opticalOcrRecognition，图片标签使用 detectLabelProcess；结果只回写后台分析结果。图片质量评分不纳入项目；当前凭证下 COS 临时读写已通过，当前 6 张 COS 业务原图已完成展示图/缩略图真实生成验证，并已验证智能标签和相似候选真实处理；无文字图片 OCR 会记录“未检测到文本”。

当前已完成：

- COS 云上持久化生成展示图和缩略图；展示图最大 2048×2048，缩略图最大 600×600，统一 WebP。
- 处理任务仅在 COS 模式且后台开关开启时入队；失败记录、有限重试、幂等 Key 和人工重试沿用后台处理任务。批量上传时可分别选择是否自动创建 OCR / 智能标签任务，也可继续由管理员对指定图片手动触发。
- 自动化测试使用模拟 SDK；当前凭证下已通过真实 COS 临时写入、读取和删除检查，并已在当前 6 张业务原图上完成数据万象展示图/缩略图真实调用验证。

后续独立确认：

- 获取图片基础信息。
- 更多批量图片的数据万象处理、历史图片迁移和生产部署验证；生产上线前可在系统设置执行“上线前检查”，后台处理任务支持失败重新入队和批量重试。
P2 使用：

- 混合检索：以图搜图、以文搜图。
- 相似图分组。
- 更复杂的人名检索。注意：人名检索可能需要白名单。

官方文档：

- 数据万象内容识别：https://cloud.tencent.com/document/product/436/134667
- 数据万象 OCR：https://cloud.tencent.com/document/product/460/96719
- 混合检索-图片检索：https://cloud.tencent.com/document/product/460/135085
- 图像检索：https://cloud.tencent.com/document/product/460/108511
- 数据万象使用限制：https://cloud.tencent.cn/document/product/460/36620

## 3. 微信支付

### 我们怎么用

微信支付只用于“赞助支持本站”，不用于购买梅西图片版权，不用于付费解锁盗版内容。

Web 端优先支持扫码/JSAPI 场景；小程序阶段再评估小程序支付。支付流程必须做到：

- 订单号唯一。
- 支付回调验签。
- 回调幂等。
- 支付日志记录 Request-ID 和第三方交易号。
- 支持手动补单。
- 退款功能 P2 再做。

官方文档：

- 小程序支付接入准备：https://pay.wechatpay.cn/doc/v3/merchant/4015459512
- JSAPI/小程序下单：https://pay.wechatpay.cn/doc/v3/merchant/4012791897
- 微信支付 PHP SDK：https://github.com/wechatpay-apiv3/wechatpay-php

## 4. 短信

### 我们怎么用

短信只用于必要场景：

- 手机号登录/绑定验证码。
- 安全验证。
- 重要账号通知。

第一版可先用邮箱登录，短信延后到 P1/P2，避免过早增加实名、签名、模板审核成本。

官方文档：

- 腾讯云短信 PHP SDK：https://cloud.tencent.com/document/product/382/56058

## 5. 微信小程序

### 我们怎么用

小程序不是第一版主战场。P2 做只读浏览、搜索、收藏、评论。后台管理、批量上传、复杂编辑和支付优先留在 Web。

合规原则：

- 不使用“官方”“后援会”等命名。
- 不以付费解锁图片作为核心功能。
- 不开放普通用户直接上传公开图片。
- 评论必须可审核、可举报、可删除。
- 小程序访问后端必须使用 HTTPS 域名，域名需备案并配置到小程序后台。

微信支付文档中也说明小程序访问商户服务需 HTTPS，并配置服务器域名。

参考：

- 微信支付小程序接入准备：https://pay.wechatpay.cn/doc/v3/merchant/4015459512
