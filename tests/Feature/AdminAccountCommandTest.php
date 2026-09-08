<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_command_creates_a_production_admin_without_exposing_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('管理员姓名', '生产管理员')
            ->expectsQuestion('管理员邮箱', 'admin@example.com')
            ->expectsQuestion('管理员密码', 'a-strong-production-password')
            ->expectsQuestion('确认管理员密码', 'a-strong-production-password')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Hash::check('a-strong-production-password', $admin->password));
        $this->assertNotSame('a-strong-production-password', $admin->password);
    }
}