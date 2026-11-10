<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\SearchQuery;
use App\Models\SearchRecommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_records_valid_queries_without_exposing_logs_to_frontend(): void
    {
        $user = User::factory()->create();

        Photo::query()->create([
            'title' => 'World Cup Final celebration',
            'status' => 'published',
            'copyright_status' => 'credited',
            'width' => 2400,
            'height' => 1600,
            'published_at' => now(),
        ]);
        Photo::query()->create([
            'title' => 'World Cup restricted original',
            'status' => 'published',
            'copyright_status' => 'restricted',
            'width' => 2400,
            'height' => 1600,
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/search?q=World%20Cup&orientation=landscape')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('search.photos.meta.total', 1)
                ->where('search.photos.data.0.title', 'World Cup Final celebration')
                ->missing('search.search_queries')
                ->missing('search_operations.search_queries')
            );

        $query = SearchQuery::query()->firstOrFail();

        $this->assertSame($user->id, $query->user_id);
        $this->assertSame('World Cup', $query->keyword);
        $this->assertSame('world cup', $query->normalized_keyword);
        $this->assertSame('search_page', $query->source);
        $this->assertSame(1, $query->result_count);
        $this->assertSame('landscape', $query->filters_json['orientation']);
        $this->assertArrayNotHasKey('q', $query->filters_json);
    }

    public function test_historical_manual_recommendations_are_not_exposed_and_are_preserved(): void
    {
        SearchRecommendation::query()->create([
            'keyword' => 'World Cup',
            'title' => '世界杯决赛',
            'description' => '从决赛开始查找',
            'is_active' => true,
            'sort_order' => 1,
            'internal_note' => '后台备注不应公开',
        ]);
        SearchRecommendation::query()->create([
            'keyword' => 'Draft Term',
            'title' => '停用搜索词',
            'is_active' => false,
        ]);

        $this->get('/search')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->missing('search_operations.recommendations')
            );
        $this->assertDatabaseCount('search_recommendations', 2);
    }

    public function test_hot_terms_only_include_valid_resultful_repeated_searches(): void
    {
        Photo::query()->create([
            'title' => 'World Cup Final celebration',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get('/search?q=World%20Cup')->assertOk();
        $this->get('/search?q=World%20Cup')->assertOk();
        $this->get('/search?q=No%20Result')->assertOk();
        $this->get('/search?q=No%20Result')->assertOk();
        $this->get('/search?q=a')->assertOk();
        $this->get('/search?q=!!!')->assertOk();

        $this->assertDatabaseCount('search_queries', 4);

        $this->get('/search')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->has('search_operations.hot_terms', 1)
                ->where('search_operations.hot_terms.0.term', 'World Cup')
                ->where('search_operations.hot_terms.0.source', 'hot')
            );
    }

    public function test_suggestions_only_return_automatic_hot_terms_and_ignore_manual_configuration(): void
    {
        SearchRecommendation::query()->create([
            'keyword' => 'World Cup',
            'title' => '世界杯决赛',
            'description' => '推荐入口',
            'is_active' => true,
            'internal_note' => '不能公开',
        ]);
        SearchQuery::query()->create([
            'keyword' => 'Training',
            'normalized_keyword' => 'training',
            'source' => 'search_page',
            'result_count' => 3,
        ]);
        SearchQuery::query()->create([
            'keyword' => 'Training',
            'normalized_keyword' => 'training',
            'source' => 'search_page',
            'result_count' => 2,
            'filters_json' => ['source_mode' => 'has'],
        ]);

        $this->getJson('/search/suggestions?q=world')
            ->assertOk()
            ->assertJsonCount(0, 'items')
            ->assertJsonMissingPath('items.0.internal_note')
            ->assertJsonMissingPath('items.0.filters_json')
            ->assertJsonMissingPath('items.0.user_id');

        $this->getJson('/search/suggestions?q=tr')
            ->assertOk()
            ->assertJsonFragment([
                'term' => 'Training',
                'source' => 'hot',
                'url' => '/search?q=Training',
            ])
            ->assertJsonMissingPath('items.0.search_count');
    }

    public function test_removed_search_operations_page_is_not_available_even_to_admins(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']))
            ->get('/admin/search-recommendations')
            ->assertNotFound();
        $this->get('/admin')->assertOk()->assertDontSee('搜索运营');
    }
}