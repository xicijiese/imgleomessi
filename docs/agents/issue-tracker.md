# Issue Tracker

## 当前选择

项目初期使用本地 Markdown 文件作为任务追踪方式。

任务目录：

```text
.scratch/messi-gallery/
```

## 使用原因

当前项目尚未初始化 Git 仓库，也没有 GitHub remote。用户是零基础开发者，前期使用本地 Markdown 更直观、低成本，适合由 AI 按 PRD 拆分任务、记录验收标准和执行状态。

## 任务文件规则

每个任务文件应包含：

- 标题
- 背景
- 要实现的用户价值
- 范围
- 不做什么
- 验收标准
- 依赖任务
- 测试要求
- 当前状态

## 迁移策略

当项目进入稳定开发阶段并创建 GitHub 仓库后，可把 `.scratch/messi-gallery/` 下的任务迁移为 GitHub Issues。

