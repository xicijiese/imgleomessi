<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_pages_receive_public_shell_payload(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        foreach ([route('profile.edit'), route('user-password.edit'), route('appearance.edit')] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $this->assertHasPublicShell($page));
        }

        if (! Features::canManageTwoFactorAuthentication()) {
            return;
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('two-factor.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $this->assertHasPublicShell($page));
    }

    public function test_settings_pages_use_public_frontend_shell_instead_of_dashboard_shell(): void
    {
        foreach (glob(resource_path('js/pages/settings/*.vue')) ?: [] as $path) {
            $this->assertStringNotContainsString(
                '@/layouts/AppLayout.vue',
                file_get_contents($path) ?: '',
                basename($path).' 不应再使用 dashboard 外壳。',
            );
        }
    }

    private function assertHasPublicShell(Assert $page): Assert
    {
        return $page
            ->has('settingsShell.site.name')
            ->has('settingsShell.navigation')
            ->has('settingsShell.footer');
    }
}
