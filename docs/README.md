# 文档索引

`docs` 目录只保留按用途划分的主文档。同一类内容不再拆成多个文件，避免阅读入口过多。

## 硬性规则

以后禁止新增新的说明类文档文件。所有产品、页面、数据库、开发、架构、部署、集成和进度内容都只能修改下方现有主文档。

## 当前主文档

| 文档 | 用途 |
|---|---|
| `docs/product/prd.md` | 产品定位、用户角色、功能优先级和验收标准。 |
| `docs/architecture/technical-architecture.md` | 技术栈、架构选择、模块边界和已接受的架构决策。 |
| `docs/database/schema.md` | 数据库逻辑模型、核心表、字段、关系和索引建议。 |
| `docs/ui/page-map.md` | 前台/后台页面清单、页面归属、P0 页面规格和 UI 质量要求。 |
| `docs/development/development-standards.md` | 开发规范、协作流程、任务拆分、测试和变更记录规则。 |
| `docs/integrations/third-party-services.md` | 腾讯云、支付、短信、搜索等第三方服务边界。 |
| `docs/ops/deployment-centos-bt.md` | Ubuntu 24.04 + 宝塔部署、运行、备份和运维要求（文件名保留历史命名）。 |
| `docs/project/development-progress.md` | 项目路线图、当前阶段、已完成事项、下一步计划和待确认问题。 |

## 推荐阅读顺序

1. `AGENTS.md`
2. `CONTEXT.md`
3. `docs/project/development-progress.md`
4. `docs/product/prd.md`
5. `docs/database/schema.md`
6. `docs/ui/page-map.md`
7. `docs/architecture/technical-architecture.md`
8. `docs/development/development-standards.md`

## 已归并的内容

- 原 UI 页面功能内容、前台 P0 规格、后台 P0 规格，合并到 `docs/ui/page-map.md`。
- 原 Phase 1 开发规格、项目初始化和项目管理说明，合并到 `docs/project/development-progress.md` 与 `docs/development/development-standards.md`。
- 原 ADR 架构决策，合并到 `docs/architecture/technical-architecture.md`。
- 原 agent 辅助说明，合并到根目录 `AGENTS.md` 和开发规范中。
