<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class GalleryTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $sortOrder = 10;

        foreach (Category::ROOT_CATEGORIES as $slug => $name) {
            $root = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'parent_id' => null,
                    'name' => $name,
                    'description' => in_array($slug, Category::REQUIRED_ROOT_SLUGS, true)
                        ? '发布时必填的主分类；子分类可由管理员继续维护。'
                        : '可选资料维度；子分类可由管理员继续维护。',
                    'sort_order' => $sortOrder,
                    'visibility' => 'public',
                    'is_system' => true,
                    'required_for_publish' => in_array($slug, Category::REQUIRED_ROOT_SLUGS, true),
                ],
            );

            Category::updateOrCreate(
                ['slug' => 'pending-'.$slug],
                [
                    'parent_id' => $root->id,
                    'name' => '待补充',
                    'description' => $name.'信息待补充，后续由管理员或编辑根据可靠线索修正。',
                    'sort_order' => 999,
                    'visibility' => 'public',
                    'is_system' => true,
                ],
            );

            $sortOrder += 10;
        }
    }
}
