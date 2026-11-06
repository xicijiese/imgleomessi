# 第三方服务对接方案

本文件描述“我们怎么用”第三方服务。所有密钥不得写入代码、文档或 Git；腾讯云 COS / 数据万象凭证可由项目管理后台加密保存，服务器只在后端和队列中读取。

## 当前生效规则（2026-09-09）

- 数据万象当前只用于展示图/缩略图派生和按需 OCR；智能标签接口已停用，不再自动入队、手动触发或回写新标签。
- local 存储不调用数据万象，使用 PHP GD 生成展示图和缩略图；COS 存储才调用数据万象派生图接口。两种模式统一生成最大 2048×2048 的展示图和最大 600×600 的缩略图，输出 WebP 并写回相同字段。
- 每张新上传图片都会创建派生图任务；任务由队列 Worker 异步消费，失败会记录原因并支持后台重试。OCR 和相似图任务不阻塞发布，只有展示图/缩略图未生成时才阻止发布。
- 上传任务只记录上传结果与异步处理状态；编辑、分类、标签和发布操作统一在图片管理完成。
- OCR 默认关闭，只有管理员主动选择时才创建任务；OCR 失败不会阻塞图片上传或发布。智能标签接口已停用，不再自动入队、手动触发或回写新标签。

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

数据万象只在后端队列中调用，不在前台用户请求中同步调用。当前项目已完成 COS + 数据万象开关、展示图/缩略图派生处理、相似图后台辅助处理和 OCR 适配；智能标签历史代码保留兼容，但当前业务已停用，不再创建或回写新标签。OCR 使用官方 PHP SDK 的 opticalOcrRecognition，结果只回写后台分析结果。图片质量评分不纳入项目；无文字图片 OCR 会记录“未检测到文本”。

当前已完成：

- COS 模式下由数据万象持久化生成展示图和缩略图；展示图最大 2048×2048，缩略图最大 600×600，统一 WebP。local 模式使用 PHP GD 生成相同规格文件。
- 每张图片都会创建派生图任务；COS 模式要求后台开启数据万象并完成 COS 配置，local 模式不依赖数据万象。失败记录、有限重试、幂等 Key 和人工重试沿用后台处理任务。
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

## 当前集成状态（2026-10-20）

- COS/数据万象图库处理及私有投稿存储适配已有实现；投稿 COS 实际权限与版本清理仍待生产测试账号验证。
- 网站分享海报、短链接、有效归因和50人勋章已完成本地实现；微信 JS-SDK 因当前公众号无权限暂缓。
- 真实支付本地实现已加入虎皮椒微信下单/验签/权益、退款流程、提醒及站内核对；查询/退款地址规范已核对，地址自动处理、响应适配和真实资金联调未完成，收款默认关闭。支付宝和发票已明确不做。
- 微信小程序及其专用登录/账号绑定与接口尚未实现；本项目现有 Web 投稿资格不等于小程序上传权限。

## 3. 微信支付

### 我们怎么用

微信支付只用于“赞助支持本站”，不用于购买梅西图片版权，不用于付费解锁盗版内容。

本轮仅使用虎皮椒微信支付，下单网关由用户指定：`https://api.xunhuweb.com/payment/do.html`。电脑扫码、手机外部浏览器，微信内暂不承诺 JSAPI；已根据 [xunhuweb 接入说明](https://github.com/xunhuweb/xunhupay_skill/blob/main/SKILL.md) 核对：三个操作共用商户后台显示的实际网关域名及同一套 APPID/密钥，无需另外申请查询或退款凭据。

实现依据为虎皮椒公开文档：

- 下单及回调：https://www.xunhupay.com/doc/api/pay.html
- 查询：https://www.xunhupay.com/doc/api/search.html
- 退款：https://www.xunhupay.com/doc/api/refund.html

接口域名以商户后台显示为准，文档示例域名不同不再作为地址不明的依据。公开查询示例含尚未明确签名序列化的嵌套 data。现有适配器只接受可明确验证的标量排序签名响应；缺失订单/金额/交易号的查询不补单，只记录异常。退款只接受整单、签名、金额、原交易及完成状态均确认的结果；errcode=0 本身不是退款完成。未知结果不重试资金请求。需要渠道响应样本补齐协议并由站长验证实际扣款和退款后才能正式开收款，不能将本地 HTTP fake 测试当成真实联调。

当前渠道接口地址：

| 操作 | 用途 | 地址 |
|---|---|---|
| 下单 | 创建付款订单，获取支付二维码/跳转地址 | `https://api.xunhuweb.com/payment/do.html` |
| 查询 | 核实付款状态，补查遗漏通知 | `https://api.xunhuweb.com/payment/query.html` |
| 退款 | 申请整单原路退款 | `https://api.xunhuweb.com/payment/refund.html` |

配置简化目标：只维护渠道 APPID、密钥、支付网关，由程序自动处理查询/退款地址。当前 PaymentSettings 默认查询/退款地址为空，后台仍有两个输入框；此项自动处理尚未编码。现版本可手工保存上述地址，但响应适配和实际资金联调完成前不开放收款。不要将“地址规则明确”写成“接口实测成功”。

参数：下单 trade_order_id、total_fee 元小数、version=1.1、notify_url；查询 out_trade_order；退款 trade_order_id、reason。url_qrcode 已是二维码图片，不二次编码。回调 form、status OD/CD/RD/UD；查询 CD 为取消而非退款。凭据通过后台加密保存，调用日志不含密钥/签名/完整原始响应。浏览器回跳不发权益。

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

2026-10-21已进入“小程序基础版”页面方案：20页、首页/图库/发现/我的四入口，包含浏览、文字搜索、统一账号、收藏/点赞/评论、分享、勋章及站内通知。用户允许保存有权访问的展示图，首版不做图片投稿，不设置付款/退款或后台入口。“只读版”为早期不准确名称，不能据此去掉互动；详细页面规格见页面地图，当前尚无客户端代码。

合规原则：

- 不使用“官方”“后援会”等命名。
- 不以付费解锁图片作为核心功能。
- 不开放普通用户直接上传公开图片。
- 评论必须可审核、可举报、可删除。
- 小程序访问后端必须使用 HTTPS 域名，域名需备案并配置到小程序后台。

微信支付文档中也说明小程序访问商户服务需 HTTPS，并配置服务器域名。

参考：

- 微信支付小程序接入准备：https://pay.wechatpay.cn/doc/v3/merchant/4015459512


## 投稿私有存储与 COS 清理（2026-10-20）

投稿暂存使用服务端代理上传和读取。可在存储设置填写 `cos.submission_bucket`（同地域独立私有桶），留空沿用图库桶但对象 ACL 强制 private。上传真实图片前先写入不含用户内容的探针，并验证 COS 原站匿名 GET 返回 403；公开读取策略覆盖 private 时拒绝传送真实图片，不静默退回公开存储。文件位置由服务器保存，切换存储设置不会改写旧记录所属桶和 Key。

使用现有 qcloud COS SDK；版本桶按准确 Key 枚举 Version 和 DeleteMarkers 并指定 VersionId 删除，非版本桶删除后确认 HEAD 为 404，失败不标记完成。凭证需要具备对应桶的私有写入、授权读取、查询版本控制、列举版本及删除版本权限。对象锁定等导致删除失败时保留异常并重试，不谎报空间已释放。

SDK 与匿名访问边界已通过模拟测试，本地文件读写/清理及真实 Worker 已验证；生产 COS 的实际桶策略、权限和版本清理仍需上线时用专门测试账号联调。代码不会自动修改存储桶策略或云端权限。


## 分享接入现状更新（2026-10-20）

用户提供的微信开发者平台“接口管理 → 接口权限与额度 → 网页应用接口 → 分享接口”截图明确显示，自定义分享消息内容、自定义分享朋友圈内容均无权限。网站 JS-SDK 自定义卡片暂缓；拥有已认证小程序不等于公众号网页权限已开放，不通过旧版接口绕过权限限制。本轮不要求用户提供 AppSecret、不改微信平台配置。

网站普通链接、微博跳转、系统分享、二维码、PNG 海报、短链接与有效回流归因已在本地实现；服务端 QR 复用现有 BaconQrCode，不新增云海报服务。COS 展示图读取复用 PhotoStorage，海报本身为服务器私有缓存；云端读取权限和实际 CDN/微信浏览器环境需生产验收。小程序页面和小程序分享仍待后续模块。

## 新微信小程序接入计划（2026-10-21，范围已确认，待开发）

2026-10-22统一方案：主动微信登录即注册，不强制邮箱密码；手机号独立授权核实，昵称头像主动填写选择。网站支持手机号或邮箱加密码，无密码账号重新验证微信身份及第二因素后设置首个密码。已有本站资料绑定/登录不覆盖；号码相同仍验证旧账号，当前已绑定微信优先识别。行为详见PRD完整方案，短信服务/验证码登录未立项。

本次网页文档仍不可读，改查官方维护的文档和代码：[API定义](https://github.com/wechat-miniprogram/api-typings/blob/master/types/wx/lib.wx.api.d.ts)中LoginSuccessCallbackResult返回code，后端换openid等；[事件定义](https://github.com/wechat-miniprogram/api-typings/blob/master/types/wx/lib.wx.event.d.ts)将ButtonGetPhoneNumber单列为open-type=getPhoneNumber回调，API隐私注释也把button.getPhoneNumber列为隐私组件；[官方头像昵称组件](https://github.com/wechat-miniprogram/mp-user-avatar)使用chooseAvatar与nickname输入。三类能力独立，不能推定新主体已获手机号资格/额度或特定收费，仍需MP-00实际后台核验。头像组件仓库已归档，仅作说明依据，不新增为运行依赖。

本项目尚未注册小程序，与此前已认证项目无关。采用原生TypeScript、20页首版、统一账号/安全解绑、游客保存、好友卡片/码海报/有效归因；不强制使用网站原注册表单。无固定上线日，完整计划见进度主文档第9节；不接支付、投稿或订阅消息。

### 注册后须核验的依赖

1. 主体、名称/头像/简介、类目及额外资质：核对图片档案、账号、评论、头像上传和分享推广；尚未确定主体，不能保证个人或组织均可完整上线。
2. 认证/备案、开发者/体验者、接口权限和额度：以新账号当前要求及费用为准，不沿用旧项目资格，不依赖公众号JS-SDK权限。
3. API的HTTPS/request域名、头像uploadFile域名、展示图/海报downloadFile域名、CDN及重定向目标；关闭域名/TLS调试豁免后真机验证。
4. wx.login/code2Session、临时code过期/重放、AppID一致性、双重验证及令牌撤销；AppSecret只在后端，身份不可由客户端自报。
5. 小程序码scene长度/字符、page路径、开发/体验/正式环境、权限/额度、access_token缓存及失效；四类公开目标真实扫码验证。
6. 保存相册、头像选择/上传、手机号授权涉及的隐私声明、主动授权与拒绝处理。先核对手机号接口主体/权限/额度；后端区分两种code，不假定wx.login自动带号码或昵称头像。接口不可用/拒绝不伪造号码，保留微信身份流程。新邮箱备用绑定还需邮件所有权验证能力和投递配置；现有功能是否可用须按实际实现核验，不默认声称已接通。
7. 评论/昵称/简介/头像的内容安全要求：敏感词和人工审核继续执行；核实是否需平台检查接口及其他能力，必要时纳入MP-05/06并在公开资料发布前完成，不能推定既有人工审核已满足平台全部要求。

### 官方入口与核验状态

核验日期2026-10-21。微信开发者网页本次读取失败，限定官方域名检索亦无可读结果；下列是待人工核验入口，不是已读取的当前政策/参数证据。注册后从新账号后台或开发者工具打开当前文档，入口迁移以官方导航为准。

- [微信公众平台](https://mp.weixin.qq.com/)：主体、类目、认证/备案、隐私及审核以实际后台为准。
- [登录流程](https://developers.weixin.qq.com/miniprogram/dev/framework/open-ability/login.html)、[code2Session](https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/user-login/code2Session.html)。
- [不限制的小程序码](https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/qrcode-link/qr-code/getUnlimitedQRCode.html)。
- [网络与域名](https://developers.weixin.qq.com/miniprogram/dev/framework/ability/network.html)。
- [隐私授权](https://developers.weixin.qq.com/miniprogram/dev/framework/user-privacy/PrivacyAuthorize.html)、[保存到相册](https://developers.weixin.qq.com/miniprogram/dev/api/media/image/wx.saveImageToPhotosAlbum.html)。
- [Laravel 12 Sanctum](https://laravel.com/framework/docs/12.x/sanctum)：已读取；支持Bearer令牌、摘要保存及过期/撤销。本项目尚未安装，具体兼容版本在实际依赖解析与测试时锁定。

真实验收：先HTTP fake覆盖身份/码接口失败分支；新AppID就绪后验证真实身份交换、双账号绑定冲突、码生成和扫码。mock通过不代表权限已开通；一次性code不盲重放，外呼失败不得留下半完成关联或虚假推广数。
