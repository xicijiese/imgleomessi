<?php

namespace Database\Seeders;

use App\Models\SponsorshipPlan;
use Illuminate\Database\Seeder;

class SponsorshipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => '月度支持',
                'slug' => 'monthly-support',
                'amount_cents' => 900,
                'duration_days' => 31,
                'badge_level' => 'supporter',
                'benefits' => "支持本站一个月的资料整理与存储维护\n获得运营守护者身份展示\n可选择是否展示在致谢墙",
                'sort_order' => 10,
            ],
            [
                'name' => '赛季支持',
                'slug' => 'season-support',
                'amount_cents' => 3000,
                'duration_days' => 120,
                'badge_level' => 'silver',
                'benefits' => "支持专题、相册和来源资料持续整理\n获得银色守护者身份\n可在个人中心管理公开展示偏好",
                'sort_order' => 20,
            ],
            [
                'name' => '年度支持',
                'slug' => 'annual-support',
                'amount_cents' => 9900,
                'duration_days' => 365,
                'badge_level' => 'gold',
                'benefits' => "支持全年服务器、存储和资料维护\n获得金色守护者身份\n进入致谢墙公开展示名单",
                'sort_order' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            SponsorshipPlan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan + ['is_active' => true],
            );
        }
    }
}
