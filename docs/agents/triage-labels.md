# Triage Labels

项目使用以下默认 triage 角色。当前本地 Markdown 阶段以文本字段记录，迁移到 GitHub Issues 后再创建同名 labels。

## 分类角色

- `bug`：已有功能出错或行为不符合预期。
- `enhancement`：新功能、体验改进或架构优化。

## 状态角色

- `needs-triage`：需要维护者评估。
- `needs-info`：需要用户补充信息。
- `ready-for-agent`：需求明确，AI agent 可独立实现。
- `ready-for-human`：需要人工判断、外部账号、资质或设计确认。
- `wontfix`：明确不做。

## 使用规则

每个任务原则上只能有一个分类角色和一个状态角色。若状态冲突，agent 必须先询问用户。

