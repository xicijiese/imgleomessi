# AGENTS.md

本文件是本项目所有 AI agent 的入口说明。任何 agent 在修改代码或文档前，必须先阅读本文件、`CONTEXT.md`、`docs/product/prd.md`、`docs/architecture/technical-architecture.md` 和相关 ADR。

## 项目定位

本项目是“梅西影像档案库 + 轻社交 + 赞助支持”产品。

核心目标是系统整理梅西相关图片资料，方便按时间、赛事、球队、相册、标签、人物同框、来源和清晰度进行查阅与考古。本站不是梅西官方站、不是后援会、不是图片版权售卖站。

## Agent skills

### Issue tracker

项目初期使用本地 Markdown 任务追踪，任务文件放在 `.scratch/messi-gallery/`。后续接入 GitHub 后可迁移到 GitHub Issues。

### Triage labels

项目使用默认 triage 状态：`needs-triage`、`needs-info`、`ready-for-agent`、`ready-for-human`、`wontfix`。

### Domain docs

项目采用 single-context 布局：根目录 `CONTEXT.md` 记录领域语言；架构决策直接合并到 `docs/architecture/technical-architecture.md`。

## AI 工作原则

1. 所有回答、注释、文档默认使用中文。
2. 先读文档再行动：不允许在不了解 PRD、架构和数据模型的情况下直接写代码。
3. 先确认范围再实现：任何开发任务都应对应 `.scratch/messi-gallery/` 下的任务文件或 PRD 中的明确范围。
4. 采用垂直切片：每次实现一个可演示的小闭环，包括数据库、后端逻辑、页面/接口和测试。
5. 不做横向大铺摊：不要先建一堆空模型、空页面、空服务，再迟迟没有可用功能。
6. 保护新手开发者：每次最终回复都要说明改了什么、如何验证、下一步建议。
7. 不擅自扩大需求：小程序、支付、以图搜图、评论、投稿等功能必须按 PRD 优先级逐步做。
8. 安全优先：不得把腾讯云密钥、微信支付证书、短信密钥写入代码或提交到仓库。
9. 测试行为，不测实现：测试应验证用户可见行为和公开接口，避免绑定内部函数名。
10. 任何高风险操作必须先说明影响：数据库迁移、删除数据、支付回调、权限改动、批量处理图片都属于高风险。
11. 禁止新增文档文件：以后不得在 `docs/` 或项目根目录新增新的说明类 Markdown 文档；任何文档内容只能合并到现有主文档中。

## 默认技术栈

- 应用架构：Laravel 单体应用，同仓维护后端、前台和后台。
- 前台：Inertia.js + Vue 3 + TypeScript + Tailwind CSS。
- 后台管理：Filament，不另行使用 Naive UI / Arco Design 自建后台。
- 数据库：MySQL 8.0 起步，后续可评估 PostgreSQL。
- 缓存与队列：Redis。
- 图片存储：通过 Laravel 文件系统抽象管理；支持本地存储和腾讯云 COS 切换，生产环境优先腾讯云 COS/CDN。
- 图片处理与识别：腾讯云数据万象。
- 搜索：第一阶段用 MySQL 索引；第二阶段接 Meilisearch 或 Typesense。
- 部署：Ubuntu 24.04 + 宝塔 LNMP，Nginx + PHP-FPM + MySQL + Redis + Supervisor。

## 禁止事项

1. 不要把项目做成 WordPress、Piwigo 或 Directus，除非 ADR 被正式修改。
2. 不要把赞助会员设计成“付费购买梅西图片版权”。
3. 不要在小程序端开放高风险付费解锁内容。
4. 不要第一版开放普通用户直接上传图片。
5. 不要把原图直链无保护地暴露给所有访客。
6. 不要在用户请求链路里同步执行 OCR、相似图检索、批量压缩等耗时任务。
7. 不要绕过后台审核直接发布 UGC 评论。
8. 不要把前台做成重型组件库堆砌；前台以 Tailwind 定制组件为主，只按需引入 PhotoSwipe、Swiper 等图库交互库。
9. 不要用 Naive UI / Arco Design 代替 Filament 搭后台，除非后续 ADR 正式改动。
10. 不要新增新的文档文件。开发说明、页面规格、阶段计划、数据库规则、架构决策和任务规则都必须写入现有主文档。

## 开发前必读顺序

1. `CONTEXT.md`
2. `docs/product/prd.md`
3. `docs/database/schema.md`
4. `docs/ui/page-map.md`
5. `docs/architecture/technical-architecture.md`
6. `docs/development/development-standards.md`
7. `docs/integrations/third-party-services.md`
8. `docs/ops/deployment-centos-bt.md`
