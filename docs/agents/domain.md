# Domain Docs

## 布局

项目采用 single-context 布局：

- 根目录 `CONTEXT.md`：项目领域语言和业务边界。
- `docs/adr/`：架构决策记录。

## 读取规则

执行以下任务前必须读取 `CONTEXT.md`：

- PRD 编写或更新
- 任务拆分
- 数据库设计
- 架构调整
- 测试策略
- 复杂 bug 诊断

涉及架构选型或重大边界调整时，必须检查 `docs/adr/`，不得重复争论已记录的决策。

