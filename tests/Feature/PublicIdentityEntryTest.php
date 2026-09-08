<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicIdentityEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_public_page_exposes_registration_flag_and_no_user(): void
    {
        $this->get('/photos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Photos/Index')
                ->where('canRegister', true)
                ->where('auth.user', null)
            );
    }

    public function test_authenticated_user_public_page_exposes_current_identity(): void
    {
        $user = User::factory()->create([
            'name' => 'Lionel Fan',
            'email' => 'fan@example.com',
        ]);

        $this->actingAs($user)
            ->get('/albums')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Albums/Index')
                ->where('canRegister', true)
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', 'Lionel Fan')
                ->where('auth.user.email', 'fan@example.com')
            );
    }

    public function test_account_entry_routes_keep_expected_boundaries(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk();
    }
}
