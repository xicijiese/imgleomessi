# 第三方服务对接方案

本文件描述“我们怎么用”第三方服务。所有密钥必须放在 `.env` 或服务器安全配置中，不得提交到 Git。

## 1. 腾讯云 COS

### 我们怎么用

- 存储原图、展示图、缩略图。
- 图片上传由 Laravel 后端控制，后台编辑员上传后写入 COS。
- 前台图片访问走 COS/CDN 域名。
- 原图默认不直接公开；前台优先使用展示图和缩略图。
- 文件 Key 使用结构化路径：

```text
photos/original/{yyyy}/{mm}/{uuid}.{ext}
photos/display/{yyyy}/{mm}/{uuid}.webp
photos/thumb/{yyyy}/{mm}/{uuid}.webp
```

### Laravel 接入

Laravel 使用文件系统抽象和 S3 兼容配置接入 COS。若 COS S3 兼容能力无法满足全部需求，再使用腾讯云 COS PHP SDK 补齐。

官方文档：

- Laravel File Storage：https://laravel.com/framework/docs/filesystem
- 腾讯云 COS 文档：https://cloud.tencent.com/document/product/436
- 腾讯云 PHP SDK：https://cloud.tencent.cn/document/sdk/PHP

## 2. 腾讯云数据万象

### 我们怎么用

数据万象只在后端队列中调用，不在前台用户请求中同步调用。

P1 使用：

- 获取图片基础信息。
- OCR 识别图片中的文字、水印、来源信息。
- 图片标签识别。
- 图片质量评估。

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

