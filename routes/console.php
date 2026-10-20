<?php

use App\Jobs\CleanPhotoSubmission;
use App\Models\PhotoImageSearch;
use App\Models\PhotoSubmission;
use App\Models\ShareLink;
use App\Models\ShareVisit;
use App\Models\User;
use App\Services\QueueWorkerStatus;
use App\Services\SponsorshipMaintenance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::call(function (): void {
    PhotoImageSearch::query()->where('expires_at', '<=', now())->each(function (PhotoImageSearch $search): void {
        if ($search->temporary_path && Storage::disk('local')->exists($search->temporary_path)
            && ! Storage::disk('local')->delete($search->temporary_path)) {
            return;
        }
        $search->delete();
    });
})->hourly()->name('image-search-cleanup')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin', function (): int {
    $name = trim((string) $this->ask('管理员姓名'));
    $email = strtolower(trim((string) $this->ask('管理员邮箱')));
    $password = (string) $this->secret('管理员密码');
    $confirmation = (string) $this->secret('确认管理员密码');

    if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('姓名不能为空，邮箱格式必须正确。');

        return 1;
    }

    if (mb_strlen($password) < 12) {
        $this->error('管理员密码至少需要 12 个字符。');

        return 1;
    }

    if ($password !== $confirmation) {
        $this->error('两次输入的管理员密码不一致。');

        return 1;
    }

    if (User::query()->where('email', $email)->exists()) {
        $this->error('该邮箱已存在，请使用其他邮箱；不要覆盖现有账号。');

        return 1;
    }

    User::query()->create([
        'name' => $name,
        'email' => $email,
        'role' => 'admin',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make($password),
    ]);

    $this->info('生产管理员创建成功，请使用刚才填写的邮箱和密码登录 /admin。');

    return 0;
})->purpose('创建生产管理员账号');

Artisan::command('submissions:cleanup', function (): void {
    $count = 0;
    PhotoSubmission::query()->whereNull('cleaned_at')->where('cleanup_at', '<=', now())
        ->whereIn('status', ['uploading', 'temporary', 'rejected', 'withdrawn', 'accepted'])
        ->orderBy('id')->chunk(100, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                CleanPhotoSubmission::dispatch($row->id)->onConnection(app(QueueWorkerStatus::class)->connection());
                $count++;
            }
        });
    $this->info('已提交 '.$count.' 个到期暂存文件清理任务。');
})->purpose('清理到期投稿暂存文件，保留审核记录');
Schedule::command('submissions:cleanup')->hourly()->withoutOverlapping();

Artisan::command('sharing:cleanup', function (): void {
    $visits = ShareVisit::where('created_at', '<', now()->subDays(90))->delete();
    $files = 0;
    ShareLink::whereNotNull('poster_path')->where('updated_at', '<', now()->subDays(90))->chunkById(100, function ($links) use (&$files): void {
        foreach ($links as $link) {
            if (Storage::disk('local')->delete($link->poster_path)) {
                $link->update(['poster_path' => null, 'poster_status' => 'none']);
                $files++;
            }
        }
    });
    $this->info("已清理 {$visits} 条过期访问明细、{$files} 份过期海报；归因与去重记录保留。");
})->purpose('清理 90 天前分享访问明细和海报缓存');
Schedule::command('sharing:cleanup')->daily()->withoutOverlapping();

Artisan::command('payments:reconcile', function (): void {
    $count = app(SponsorshipMaintenance::class)->reconcile();
    $this->info('已提交 '.$count.' 个真实订单补查任务。');
})->purpose('补查最近一天的真实赞助待支付订单，排除模拟记录');
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Artisan::command('payments:remind', function (): void {
    $count = app(SponsorshipMaintenance::class)->remind();
    $this->info('已发送 '.$count.' 条到期提醒。');
})->purpose('提前 7 天及 1 天发送一次支持身份到期站内提醒');
Schedule::command('payments:remind')->hourly()->withoutOverlapping();
