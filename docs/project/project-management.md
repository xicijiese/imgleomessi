# 项目管理约定

## 1. 当前阶段

当前处于开发前初始化阶段。目标是把产品边界、技术架构、数据库、页面、第三方服务和 AI 协作规则写清楚，再开始正式编码。

## 2. 目录结构建议

未来 Laravel 项目建议结构：

```text
app/
  Actions/
  Filament/
  Jobs/
  Models/
  Policies/
  Services/
  Support/
database/
  migrations/
  seeders/
resources/
  views/
  css/
  js/
routes/
  web.php
  api.php
tests/
  Feature/
  Unit/
docs/
  product/
  architecture/
  database/
  ui/
  integrations/
  ops/
  project/
  adr/
.scratch/
  messi-gallery/
```

## 3. 分支规范

项目初期如果还没有 Git，可以先在本地开发。初始化 Git 后使用：

- `main`：稳定可部署分支。
- `develop`：日常集成分支，可选。
- `feature/<short-name>`：功能分支。
- `fix/<short-name>`：bug 修复分支。
- `docs/<short-name>`：文档分支。

新手阶段可以简化为 `main + feature/*`。

## 4. 任务拆分规则

任务必须使用垂直切片：

- 一个任务要能独立演示。
- 一个任务应覆盖必要的数据、后端、页面/接口和测试。
- 不创建“只建数据库”“只建页面”“只写模型”这种长期不可演示的横向任务。

任务模板：

```markdown
# 标题

## 背景

## 用户价值

## 范围

## 不做什么

## 验收标准

- [ ] ...

## 测试要求

## 依赖

## 状态
needs-triage
```

## 5. 变更记录规则

创建 `CHANGELOG.md` 后按日期记录：

```markdown
## 2026-08-24

### Added

- 新增图片上传后台。

### Changed

- 调整图片版权状态字段。

### Fixed

- 修复搜索筛选状态丢失。
```

## 6. 决策记录规则

重大决策写入 `docs/adr/`：

- 技术栈变更。
- 数据库从 MySQL 换 PostgreSQL。
- 前台从 Blade/Livewire 换 Next.js。
- 是否开放用户上传。
- 是否做小程序支付。

ADR 文件命名：

```text
docs/adr/0001-use-laravel-filament.md
```

## 7. AI 协作流程

每个开发任务按以下流程：

1. AI 读取 `AGENTS.md`、`CONTEXT.md` 和相关 docs。
2. AI 总结任务目标和不做什么。
3. AI 检查现有代码。
4. AI 写一个小计划。
5. AI 实现垂直切片。
6. AI 运行测试或说明无法测试的原因。
7. AI 汇报变更、验证方式、风险和下一步。

