<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicStaticPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_pages_can_be_visited_by_guests(): void
    {
        $pages = [
            '/about' => '关于本站',
            '/copyright' => '版权说明',
            '/takedown' => '下架申请',
            '/privacy' => '隐私政策',
            '/terms' => '用户协议',
        ];

        foreach ($pages as $url => $title) {
            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Static/Show')
                    ->where('staticPage.page.title', $title)
                    ->where('staticPage.navigation.0.url', '/')
                    ->where('staticPage.footer.links.0.url', '/about')
                );
        }
    }

    public function test_static_pages_keep_confirmed_compliance_boundaries(): void
    {
        $this->get('/copyright')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/Show')
                ->where('staticPage.page.notice', '任何页面都不应被理解为图片版权转让、授权销售或官方背书。')
                ->where('staticPage.page.sections.0.items.0', '图片版权、肖像权、商标权和赛事相关权利归对应权利人所有。')
            );

        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('staticPage.page.notice', '本站不是梅西官方站，不代表梅西本人、俱乐部、国家队或任何官方机构。')
            );
    }

    public function test_takedown_page_uses_configured_contact_email(): void
    {
        Setting::setValue('site', 'basic', [
            'name' => '梅西影像档案库',
            'logo_path' => null,
            'search_placeholder' => '搜索图片、相册、赛事或年份',
            'copyright_text' => '梅西影像档案库',
            'icp_text' => null,
            'contact_email' => 'rights@example.com',
        ]);

        $this->get('/takedown')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Static/Show')
                ->where('staticPage.page.contact_email', 'rights@example.com')
            );
    }

    public function test_homepage_footer_exposes_static_page_links_by_default(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('home.footer.links.0.url', '/about')
                ->where('home.footer.links.1.url', '/copyright')
                ->where('home.footer.links.2.url', '/takedown')
                ->where('home.footer.links.3.url', '/privacy')
                ->where('home.footer.links.4.url', '/terms')
            );
    }
}
