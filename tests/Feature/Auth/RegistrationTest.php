<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '13800138000',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('me.show', absolute: false));
    }

    public function test_phone_number_is_required_for_registration(): void
    {
        $response = $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'missing-phone@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_phone_number_must_use_mainland_mobile_format(): void
    {
        $response = $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'invalid-phone@example.com',
                'phone' => '123456',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_phone_number_must_be_unique_and_valid(): void
    {
        User::factory()->create(['phone' => '13800138000']);

        $response = $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'duplicate-phone@example.com',
                'phone' => '13800138000',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_empty_or_invalid_contact_fields_explain_failure_without_creating_user(): void
    {
        foreach ([
            ['', '', '请填写邮箱地址，邮箱地址为必填项。', '请填写手机号码，手机号码为必填项。'],
            ['not-an-email', '12345678901', '邮箱地址格式不正确，请填写有效的邮箱地址。', '手机号码格式不正确，请填写以1开头、第二位为3至9的11位中国大陆手机号码。'],
            ['invalid@', '1380013800', '邮箱地址格式不正确，请填写有效的邮箱地址。', '手机号码格式不正确，请填写以1开头、第二位为3至9的11位中国大陆手机号码。'],
            ['invalid', '138001380000', '邮箱地址格式不正确，请填写有效的邮箱地址。', '手机号码格式不正确，请填写以1开头、第二位为3至9的11位中国大陆手机号码。'],
        ] as [$email, $phone, $emailError, $phoneError]) {
            $this->from(route('register'))->post(route('register.store'), [
                'name' => '注册测试', 'email' => $email, 'phone' => $phone,
                'password' => 'password', 'password_confirmation' => 'password',
            ])->assertRedirect(route('register'))->assertSessionHasErrors([
                'email' => $emailError, 'phone' => $phoneError,
            ]);
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
        }
    }
}
