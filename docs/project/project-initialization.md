# 项目初始化检查清单

本文件用于指导零基础开发者和后续 AI agent 从“空目录”进入“可开发项目”的过程。

## 1. 当前状态

截至 2026-08-24，当前目录仍处于开发前文档初始化阶段，尚未创建 Laravel 项目，尚未初始化 Git 仓库。

已完成：

- 项目定位文档。
- PRD。
- 技术架构文档。
- 数据库设计文档。
- UI 页面映射文档。
- 第三方服务对接方案。
- CentOS + 宝塔部署文档。
- AI agent 工作规则。
- 本地任务追踪约定。

## 2. 正式编码前必须完成

1. 初始化 Git 仓库。
2. 创建 `.gitignore`。
3. 创建 Laravel 项目。
4. 选择 Vue Starter Kit，启用 Inertia + Vue 3 + TypeScript + Tailwind CSS。
5. 安装 Filament。
6. 配置本地数据库。
7. 配置 `.env.example`。
8. 创建基础测试。
9. 建立第一批本地任务文件。

## 3. 推荐初始化命令顺序

以下命令仅作为未来执行参考，不要求现在立即运行：

```bash
git init
laravel new . --vue
composer require filament/filament
php artisan filament:install --panels
php artisan make:filament-user
npm install
npm run build
php artisan test
```

如果目录中已有文档，创建 Laravel 项目时要避免覆盖现有 `docs/`、`AGENTS.md`、`CONTEXT.md`。

## 4. 第一阶段开发目标

第一阶段只做 P0：

- 管理员/编辑员后台。
- 图片上传到 COS。
- 相册、标签、赛事、球队基础管理。
- Inertia + Vue 前台图库浏览。
- 基础搜索和筛选。

评论、赞助、数据万象、小程序都不要插队。

## 5. 新手注意事项

- 不要一次性让 AI 做完整大项目。
- 每次只让 AI 做一个垂直切片。
- 每次开发后都要求 AI 运行测试。
- 不理解命令时先问 AI “这条命令会改什么”。
- 涉及支付、删除、数据库迁移时必须先备份或确认。
