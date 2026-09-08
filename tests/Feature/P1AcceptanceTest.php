<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_p1_pages_are_accessible_to_guests(): void
    {
        foreach ($this->publicGuestPaths() as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_private_and_admin_p1_pages_keep_guest_boundaries(): void
    {
        foreach ($this->privateUserPaths() as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }

        foreach ($this->adminPaths() as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
    }

    public function test_authenticated_settings_pages_stay_in_public_shell(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        foreach (['/settings/profile', '/settings/password', '/settings/appearance'] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('settingsShell.site')
                    ->has('settingsShell.navigation')
                    ->has('settingsShell.footer')
                );
        }
    }

    public function test_sitemap_and_robots_are_served_by_dynamic_routes(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee('<loc>http://localhost/photos</loc>', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Allow: /', false)
            ->assertSee('Disallow: /admin/', false)
            ->assertSee('Disallow: /me/', false)
            ->assertSee('Disallow: /settings/', false)
            ->assertSee('Disallow: /search', false)
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false);
    }

    /**
     * @return array<int, string>
     */
    private function publicGuestPaths(): array
    {
        return [
            '/',
            '/photos',
            '/search',
            '/albums',
            '/topics',
            '/rankings',
            '/support',
            '/supporters',
            '/about',
            '/copyright',
            '/takedown',
            '/privacy',
            '/terms',
            '/sitemap.xml',
            '/robots.txt',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function privateUserPaths(): array
    {
        return [
            '/me',
            '/me/favorites',
            '/me/comments',
            '/me/reports',
            '/me/notifications',
            '/me/sponsorships',
            '/me/badges',
            '/settings/profile',
            '/settings/password',
            '/settings/two-factor',
            '/settings/appearance',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function adminPaths(): array
    {
        return [
            '/admin',
            '/admin/photos',
            '/admin/interaction-stats',
            '/admin/processing-jobs',
        ];
    }
}
