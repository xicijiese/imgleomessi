<?php

namespace Tests\Feature\Auth;

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
        \App\Models\User::factory()->create(['phone' => '13800138000']);

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
}
