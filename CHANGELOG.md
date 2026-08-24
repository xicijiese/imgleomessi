# Changelog

## 2026-08-24

### Added

- 新增项目开发前文档包。
- 新增 `AGENTS.md`，定义 AI agent 工作原则。
- 新增 `CONTEXT.md`，定义项目领域语言。
- 新增 PRD、技术架构、数据库设计、UI 页面映射、第三方服务、部署运维、项目管理和开发规范文档。
- 新增 ADR-0001，记录采用 Laravel + Filament 的架构决策。
- 新增 `.scratch/messi-gallery/README.md`，作为本地任务追踪入口。

### Changed

- 技术架构从 `Blade/Livewire 起步` 调整为 `Laravel + Inertia.js + Vue 3 + TypeScript + Tailwind CSS + Filament`。
- 明确后台第一版即使用 Filament，不使用 Naive UI / Arco Design 自建后台。
