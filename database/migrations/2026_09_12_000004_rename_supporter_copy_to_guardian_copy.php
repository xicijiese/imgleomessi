<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replacements = [
            "支持本站一个月的资料整理与存储维护
获得支持者身份展示
可选择是否展示在支持者墙" => "支持本站一个月的资料整理与存储维护
获得运营守护者身份展示
可选择是否展示在致谢墙",
            "支持专题、相册和来源资料持续整理
获得银色支持者身份
可在个人中心管理公开展示偏好" => "支持专题、相册和来源资料持续整理
获得银色守护者身份
可在个人中心管理公开展示偏好",
            "支持全年服务器、存储和资料维护
获得金色支持者身份
进入支持者墙公开展示名单" => "支持全年服务器、存储和资料维护
获得金色守护者身份
进入致谢墙公开展示名单",
        ];

        foreach ($replacements as $old => $new) {
            DB::table("sponsorship_plans")->where("benefits", $old)->update(["benefits" => $new]);
        }

        DB::table("badges")->where("name", "本站支持者")->update(["name" => "本站守护者"]);
    }

    public function down(): void
    {
        $replacements = [
            "支持本站一个月的资料整理与存储维护
获得运营守护者身份展示
可选择是否展示在致谢墙" => "支持本站一个月的资料整理与存储维护
获得支持者身份展示
可选择是否展示在支持者墙",
            "支持专题、相册和来源资料持续整理
获得银色守护者身份
可在个人中心管理公开展示偏好" => "支持专题、相册和来源资料持续整理
获得银色支持者身份
可在个人中心管理公开展示偏好",
            "支持全年服务器、存储和资料维护
获得金色守护者身份
进入致谢墙公开展示名单" => "支持全年服务器、存储和资料维护
获得金色支持者身份
进入支持者墙公开展示名单",
        ];

        foreach ($replacements as $old => $new) {
            DB::table("sponsorship_plans")->where("benefits", $old)->update(["benefits" => $new]);
        }

        DB::table("badges")->where("name", "本站守护者")->update(["name" => "本站支持者"]);
    }
};
