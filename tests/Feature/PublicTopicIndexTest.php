<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\HomepageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicTopicIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_topic_index_with_configured_topics(): void
    {
        $this->saveTopicModule([
            'display_count' => 1,
            'items' => [
                [
                    'enabled' => true,
                    'title' => '金球奖',
                    'url' => '/topics/ballon-dor',
                    'cover_image_path' => 'settings/topics/ballon-dor.webp',
                ],
                [
                    'enabled' => true,
                    'title' => '世界杯',
                    'url' => '/topics/world-cup',
                    'cover_image_path' => 'settings/topics/world-cup.webp',
                ],
            ],
        ]);

        $this->get('/topics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Topics/Index')
                ->where('topicIndex.site.name', '梅西影像档案库')
                ->has('topicIndex.navigation')
                ->has('topicIndex.topics.data', 2)
                ->where('topicIndex.topics.data.0.title', '金球奖')
                ->where('topicIndex.topics.data.0.url', '/topics/ballon-dor')
                ->where('topicIndex.topics.data.0.cover_image_url', '/storage/settings/topics/ballon-dor.webp')
                ->where('topicIndex.topics.data.1.title', '世界杯')
                ->where('topicIndex.topics.data.1.url', '/topics/world-cup')
            );
    }

    public function test_topic_index_filters_disabled_blank_and_invalid_topic_links(): void
    {
        $this->saveTopicModule([
            'items' => [
                [
                    'enabled' => true,
                    'title' => '有效专题',
                    'url' => '/topics/valid',
                ],
                [
                    'enabled' => false,
                    'title' => '关闭专题',
                    'url' => '/topics/disabled',
                ],
                [
                    'enabled' => true,
                    'title' => '',
                    'url' => '/topics/blank-title',
                ],
                [
                    'enabled' => true,
                    'title' => '外链专题',
                    'url' => 'https://example.com/topics/external',
                ],
                [
                    'enabled' => true,
                    'title' => '非专题链接',
                    'url' => '/albums',
                ],
            ],
        ]);

        $this->get('/topics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Topics/Index')
                ->has('topicIndex.topics.data', 1)
                ->where('topicIndex.topics.data.0.title', '有效专题')
                ->where('topicIndex.topics.data.0.url', '/topics/valid')
            );
    }

    public function test_topic_index_empty_state_returns_ok_without_topics(): void
    {
        $this->get('/topics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Topics/Index')
                ->has('topicIndex.topics.data', 0)
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveTopicModule(array $overrides): void
    {
        $defaults = HomepageSettings::defaults()['topic_module'];

        Setting::setValue('home', 'topic_module', array_replace_recursive($defaults, $overrides));
    }
}
