# ADR-0001：采用 Laravel + Inertia + Vue + Filament 作为主架构

## 状态

Accepted

## 背景

项目最初讨论过 WordPress、Piwigo、Directus、Payload、Laravel + Filament 等方案。需求从纯图库逐步明确为“图库 + 轻社交 + 赞助支持 + 后台权限 + 图片智能处理 + 后续小程序”。

## 决策

采用 Laravel 单体应用作为主架构。前台使用 Inertia.js + Vue 3 + TypeScript + Tailwind CSS，后台使用 Filament。

## 理由

- Laravel 适合用户、权限、评论、支付、队列、文件、API 等完整业务系统。
- Inertia 允许前台使用 Vue 3 构建更顺滑的图库交互，同时继续使用 Laravel 路由、Controller、认证和部署方式。
- Vue 3 对新手和 AI 协作较友好，适合图库筛选、灯箱、收藏、评论、移动端交互等场景。
- Filament 能快速搭建后台，降低新手和 AI 开发成本。
- 单体应用降低部署和调试复杂度。
- 后续可平滑为小程序提供 API。
- COS、数据万象、微信支付、短信都能通过 PHP SDK 或 HTTP API 接入。

## 不采用方案

- WordPress：过于臃肿，且业务模型会被 CMS 结构拖累。
- Piwigo：适合传统图库，但不适合轻社交、支付和复杂业务扩展。
- Directus：适合内容后台，但普通用户、评论、支付仍需独立业务后端。

## 后果

- 项目需要实际开发，而不是安装模板即可完成。
- 新手需要依赖 AI 按垂直切片逐步实现。
- 第一版必须控制范围，避免一次性加入全部高级功能。
- 部署时除 PHP/Composer 外，还需要 Node/npm 构建前台资源。
- 若后续启用 Inertia SSR，需要额外用 Supervisor 守护 SSR 进程。
