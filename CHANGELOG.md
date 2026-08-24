# Changelog

## 2026-08-24

### Added

- 新增项目开发前文档包。
- 新增 `AGENTS.md`，定义 AI agent 工作原则。
- 新增 `CONTEXT.md`，定义项目领域语言。
- 新增 PRD、技术架构、数据库设计、UI 页面映射、第三方服务、部署运维、项目管理和开发规范文档。
- 新增 ADR-0001，记录采用 Laravel + Filament 的架构决策。
- 新增 `.scratch/messi-gallery/README.md`，作为本地任务追踪入口。
- 初始化 Laravel 12 项目骨架，采用 Inertia.js + Vue 3 + TypeScript + Tailwind CSS。
- 安装并生成 Filament 5 后台面板，后台入口默认为 `/admin`。
- 生成本地 `.env` 的 `APP_KEY`，并完成前端依赖安装与基础构建验证。

### Changed

- 技术架构从 `Blade/Livewire 起步` 调整为 `Laravel + Inertia.js + Vue 3 + TypeScript + Tailwind CSS + Filament`。
- 明确后台第一版即使用 Filament，不使用 Naive UI / Arco Design 自建后台。
- 通过 `npm audit fix` 更新前端锁文件，将 npm 审计告警降为 0。

### Verified

- `npm run build` 通过。
- `php artisan test` 通过，41 个测试、132 个断言全部成功。
