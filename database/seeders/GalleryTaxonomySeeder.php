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
                    'description' => '系统固定主分类，Phase 1 不允许在后台新增第 8 个主分类。',
                    'sort_order' => $sortOrder,
                    'visibility' => 'public',
                    'is_system' => true,
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
