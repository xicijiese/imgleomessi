<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BrandedErrorPagesTest extends TestCase
{
    public function test_missing_page_is_branded_and_keeps_404_status(): void
    {
        $this->get('/missing-archive-page-test')->assertNotFound()->assertSee('这一页，暂未找到')->assertSee('noindex, nofollow')->assertSee('返回首页');
    }

    public function test_inertia_missing_page_requests_full_navigation_instead_of_error_modal(): void
    {
        $this->withHeaders(['X-Inertia' => 'true'])->get('/missing-archive-page-test')
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/missing-archive-page-test'));
    }

    public function test_json_errors_are_not_replaced_with_html(): void
    {
        $this->getJson('/missing-archive-page-test')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_production_errors_have_safe_independent_pages_and_preserve_retry_header(): void
    {
        config(['app.debug' => false]);
        foreach ([403, 405, 410, 419, 429, 500, 502, 503, 504] as $status) {
            Route::get('/test-error-'.$status, fn () => abort($status, 'private-debug-marker', ['Retry-After' => '60']));
            $this->get('/test-error-'.$status)->assertStatus($status)->assertSee('梅西影像档案库')->assertDontSee('private-debug-marker')->assertHeader('Retry-After', '60');
        }
    }

    public function test_static_fallbacks_are_available_without_build_dependencies(): void
    {
        $this->artisan('errors:build')->assertSuccessful();
        foreach ([404, 419, 429, 500, 502, 503, 504] as $status) {
            $html = file_get_contents(public_path('errors/'.$status.'.html'));
            $this->assertStringContainsString('noindex, nofollow', $html);
            $this->assertStringNotContainsString('/build/', $html);
            $this->assertStringContainsString('返回首页', $html);
        }
    }
}
