<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

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