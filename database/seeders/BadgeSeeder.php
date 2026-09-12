<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'name' => '档案馆访客',
                'slug' => 'archive-visitor',
                'description' => '注册账号后自动获得，代表正式加入梅西影像档案库。',
                'icon_key' => 'user-round',
                'color' => '#3da9fc',
                'rule_type' => 'registered',
                'rule_threshold' => 1,
                'sort_order' => 10,
            ],
            [
                'name' => '第一张收藏',
                'slug' => 'first-favorite',
                'description' => '收藏任意一张公开图片后获得。',
                'icon_key' => 'heart',
                'color' => '#ef4565',
                'rule_type' => 'favorites_count',
                'rule_threshold' => 1,
                'sort_order' => 20,
            ],
            [
                'name' => '影像讨论者',
                'slug' => 'discussion-starter',
                'description' => '发布的普通评论通过审核后获得。',
                'icon_key' => 'message-circle',
                'color' => '#faae2b',
                'rule_type' => 'comments_count',
                'rule_threshold' => 1,
                'sort_order' => 30,
            ],
            [
                'name' => '本站守护者',
                'slug' => 'site-supporter',
                'description' => '完成一次赞助支持后获得。',
                'icon_key' => 'heart-handshake',
                'color' => '#00a896',
                'rule_type' => 'supporter',
                'rule_threshold' => 1,
                'sort_order' => 40,
            ],
            [
                'name' => '人工精选贡献',
                'slug' => 'curator-pick',
                'description' => '由后台管理员手动发放，用于记录阶段性贡献或特殊纪念。',
                'icon_key' => 'sparkles',
                'color' => '#6246ea',
                'rule_type' => 'manual',
                'rule_threshold' => null,
                'sort_order' => 50,
            ],
        ];

        foreach ($badges as $badge) {
            Badge::query()->updateOrCreate(
                ['slug' => $badge['slug']],
                [...$badge, 'is_active' => true],
            );
        }
    }
}
