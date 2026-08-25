# Changelog

## 2026-08-25

### Added

- 新增 `docs/project/development-progress.md`，记录项目阶段、MVP 范围、开发进度、下一步计划和待确认问题。

### Changed

- 将 `docs/project/development-progress.md` 升级为项目路线图与开发进度的唯一主入口，补充全项目阶段路线图。
- 将 Laravel 默认语言、回退语言和 Faker 语言设置为 `zh_CN`，Filament 后台默认显示简体中文。
- 确认第一版 MVP 只做图库核心闭环，评论、VIP、微信小程序、OCR、相似图去重和以图搜图暂不进入第一版。
- 确认 Phase 1 只落图库核心表，后续社交、赞助、支付、AI 分析和系统任务表先保留在数据库设计文档中。
- 确认 Phase 1 分类模型采用 7 个必选主分类 + 子分类 + 多标签，并将图库核心表调整为 `albums`、`categories`、`album_category`、`album_photo`、`photo_category`、`tags`、`photo_tag`。
- 确认 7 个必选主分类为：生涯阶段、赛事、赛季、年份、场景、图片类型、来源平台；每个主分类预置 `待补充` 子分类，便于资料不完整时先录入、后修正。
- 确认 Phase 1 主分类固定，不允许后台随意新增第 8 个主分类；子分类允许后台自由新增、编辑和排序。
- 确认 Phase 1 加入相册/专题功能；相册必须选择 7 个主分类下的子分类，图片加入相册后分类完全同步相册，相册内图片不额外补充分类。
- 确认 Phase 1 标签允许后台自由新增、编辑和排序，但每个标签必须归属一个标签类型。
- 确认 Phase 1 预置 8 个标签类型：动作、情绪、画质、人物关系、荣誉、画面内容、服装/装备、地点。
- 确认 Phase 1 上传图片时不做重复检测、哈希拦截、感知哈希检测或相似图检测；同一视觉图片重复上传时视为多条独立图片记录。
- 确认相似图去重后置为后台管理能力，不阻塞第一版上传流程。
- 确认 Phase 1 支持批量上传到相册，并在上传后进入批量整理页面，逐图补充标题、说明、标签和发布状态。

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
