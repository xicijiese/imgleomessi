# 分类与标签后台字典管理

## 背景

Phase 1 已确认使用 8 个固定主分类、子分类和多标签作为图库检索地基。相册和图片上传都依赖这些字典，因此需要先实现分类与标签后台管理。

## 用户价值

管理员可以先维护图库基础字典，后续新建相册和上传图片时就能稳定选择分类和标签。

## 范围

- categories 表。
- tags 表。
- Category 模型。
- Tag 模型。
- GalleryTaxonomySeeder。
- Filament 子分类管理资源。
- Filament 标签管理资源。
- Feature Test。

## 不做什么

- 不做图片上传。
- 不做相册管理。
- 不做前台检索。
- 不做评论、收藏、赞助、小程序。
- 不创建 teams、competitions、matches 独立表。

## 验收标准

- [x] Seeder 生成 8 个固定主分类。
- [x] 每个主分类生成待补充子分类。
- [x] 后台可访问子分类管理页。
- [x] 后台可访问标签管理页。
- [x] 测试通过。

## 测试要求

运行 php artisan test。

## 依赖

Laravel 12、Filament 5、当前用户登录能力。

## 状态

已完成。该任务是历史基础切片；当前继续遵循 8 个固定主分类、子分类可维护、系统分类不可删除和有关联分类友好拦截规则。后续状态以 docs/project/development-progress.md 为准。