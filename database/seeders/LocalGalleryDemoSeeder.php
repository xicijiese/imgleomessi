<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Badge;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Photo;
use App\Models\PhotoFavorite;
use App\Models\PhotoLike;
use App\Models\PhotoShare;
use App\Models\Setting;
use App\Models\Source;
use App\Models\SponsorshipOrder;
use App\Models\SponsorshipPlan;
use App\Models\Tag;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\HomepageSettings;
use App\Services\PhotoProcessingService;
use App\Services\SponsorshipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalGalleryDemoSeeder extends Seeder
{
    private const SOURCE_DIR = '.scratch/messi-gallery/test-images';

    private const STORAGE_DIR = 'demo-gallery';

    public function run(): void
    {
        $images = $this->sourceImages();

        if ($images === []) {
            $this->command?->warn('未找到测试图片，请先把图片放入 '.self::SOURCE_DIR);

            return;
        }

        $this->call(GalleryTaxonomySeeder::class);
        $this->call(SponsorshipPlanSeeder::class);
        $this->call(BadgeSeeder::class);

        $categories = $this->demoCategories();
        $tags = $this->demoTags();
        $source = Source::query()->firstOrCreate(
            ['original_url' => 'local://messi-gallery-demo'],
            [
                'published_at' => now(),
                'copyright_note' => '本地测试素材，仅用于开发环境页面效果验收。',
                'internal_note' => '由 LocalGalleryDemoSeeder 根据 .scratch/messi-gallery/test-images 生成。',
                'is_enabled' => true,
            ],
        );

        $photos = collect($images)
            ->map(fn (string $path, int $index): Photo => $this->upsertPhoto($path, $index, $categories, $tags, $source))
            ->values();

        $albums = collect($this->albumPlans($categories))
            ->map(function (array $plan, int $index) use ($photos): Album {
                $slice = $photos->slice($index * 6, 6)->values();

                if ($slice->count() < 3) {
                    $slice = $photos->shuffle()->take(min(6, $photos->count()))->values();
                }

                return $this->upsertAlbum($plan, $slice);
            })
            ->values();

        $this->saveHomepageSettings($photos, $albums);
        $processingJobCount = $this->seedDemoProcessing($photos);
        $users = $this->demoUsers();
        $commentCount = $this->seedDemoComments($photos, $users);
        $interactionCount = $this->seedDemoInteractions($photos, $users);
        $supporterCount = $this->seedDemoSponsorships($users);
        $badgeCount = $this->seedDemoBadges($users);

        $this->command?->info(sprintf(
            '已生成本地演示数据：%d 张图片、%d 个相册、%d 个首页专题、%d 个图片处理任务、%d 个模拟用户、%d 条评论/纠错记录、%d 条互动排行记录、%d 个支持者赞助订单、%d 个用户勋章记录。',
            $photos->count(),
            $albums->count(),
            min(4, $albums->count()),
            $processingJobCount,
            count($users),
            $commentCount,
            $interactionCount,
            $supporterCount,
            $badgeCount,
        ));
    }

    /**
     * @return array<int, string>
     */
    private function sourceImages(): array
    {
        $dir = base_path(self::SOURCE_DIR);

        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($file): bool => in_array(Str::lower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
            ->map(fn ($file): string => $file->getPathname())
            ->shuffle()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, Category>>
     */
    private function demoCategories(): array
    {
        $plans = [
            'career-stage' => [
                'argentina-era' => '阿根廷国家队',
                'barcelona-era' => '巴萨时期',
                'inter-miami-era' => '迈阿密国际',
            ],
            'competition' => [
                'world-cup' => '世界杯',
                'copa-america' => '美洲杯',
                'club-friendly' => '俱乐部赛事',
            ],
            'season' => [
                'season-2022-2023' => '2022-2023',
                'season-2023-2024' => '2023-2024',
                'season-2024-2025' => '2024-2025',
            ],
            'year' => [
                'year-2022' => '2022',
                'year-2023' => '2023',
                'year-2024' => '2024',
                'year-2025' => '2025',
            ],
            'scene' => [
                'match-scene' => '比赛现场',
                'training-scene' => '训练备战',
                'award-scene' => '颁奖典礼',
            ],
            'image-type' => [
                'match-photo' => '比赛图',
                'portrait-photo' => '肖像图',
                'celebration-photo' => '庆祝图',
                'group-photo' => '合影图',
            ],
            'source-platform' => [
                'instagram-source' => 'Instagram',
                'x-source' => 'X / Twitter',
                'local-test-source' => '本地测试素材',
            ],
        ];

        $result = [];

        foreach ($plans as $rootSlug => $children) {
            $root = Category::query()->where('slug', $rootSlug)->firstOrFail();
            $sortOrder = 10;

            foreach ($children as $slug => $name) {
                $result[$rootSlug][$slug] = Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'parent_id' => $root->id,
                        'name' => $name,
                        'description' => '本地演示数据分类，用于前台页面效果验收。',
                        'sort_order' => $sortOrder,
                        'visibility' => 'public',
                        'is_system' => false,
                    ],
                );

                $sortOrder += 10;
            }
        }

        return $result;
    }

    /**
     * @return array<int, Tag>
     */
    private function demoTags(): array
    {
        $plans = [
            ['name' => '庆祝', 'type' => '动作'],
            ['name' => '冲刺', 'type' => '动作'],
            ['name' => '专注', 'type' => '情绪'],
            ['name' => '高光', 'type' => '画质'],
            ['name' => '队友同框', 'type' => '人物关系'],
            ['name' => '冠军时刻', 'type' => '荣誉'],
            ['name' => '赛前热身', 'type' => '画面内容'],
            ['name' => '球衣', 'type' => '服装/装备'],
            ['name' => '球场', 'type' => '地点'],
        ];

        return collect($plans)
            ->map(fn (array $plan, int $index): Tag => Tag::query()->updateOrCreate(
                ['name' => $plan['name']],
                [
                    'type' => $plan['type'],
                    'description' => '本地演示标签，用于前台筛选和详情页展示。',
                    'sort_order' => ($index + 1) * 10,
                ],
            ))
            ->all();
    }

    /**
     * @param  array<string, array<string, Category>>  $categories
     * @param  array<int, Tag>  $tags
     */
    private function upsertPhoto(string $path, int $index, array $categories, array $tags, Source $source): Photo
    {
        $basename = basename($path);
        $storedFilename = 'demo-'.$basename;
        $storagePath = self::STORAGE_DIR.'/'.$storedFilename;

        Storage::disk('public')->put($storagePath, File::get($path));

        [$width, $height] = @getimagesize($path) ?: [null, null];
        $publishedAt = Carbon::now()->subDays($index)->setTime(10 + ($index % 8), 0);

        $photo = Photo::query()->firstOrNew(['stored_filename' => $storedFilename]);

        if (! $photo->exists) {
            $photo->uuid = (string) Str::uuid();
        }

        $photo->fill([
            'title' => $this->photoTitle($index),
            'description' => '本地测试图片，用于验证首页、图库、相册、专题和图片详情页的视觉效果。',
            'original_filename' => $basename,
            'taken_at' => $publishedAt,
            'event_date' => $publishedAt->toDateString(),
            'source_id' => $source->id,
            'copyright_status' => 'credited',
            'status' => 'published',
            'width' => $width,
            'height' => $height,
            'mime_type' => File::mimeType($path),
            'file_size' => File::size($path),
            'original_key' => $storagePath,
            'display_key' => $storagePath,
            'thumbnail_key' => $storagePath,
            'published_at' => $publishedAt,
        ]);
        $photo->save();

        $photo->categories()->sync($this->categorySet($categories, $index));
        $photo->tags()->sync(collect($tags)->shuffle()->take(3)->pluck('id')->all());

        return $photo;
    }

    private function photoTitle(int $index): string
    {
        $titles = [
            '测试图：冠军庆祝瞬间',
            '测试图：赛前专注时刻',
            '测试图：球场奔跑镜头',
            '测试图：队友同框',
            '测试图：训练备战',
            '测试图：赛后致意',
            '测试图：高光肖像',
            '测试图：看台与球场',
        ];

        return $titles[$index % count($titles)].' #'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, array<string, Category>>  $categories
     * @return array<int, int>
     */
    private function categorySet(array $categories, int $index): array
    {
        $rotation = [
            ['argentina-era', 'world-cup', 'season-2022-2023', 'year-2022', 'match-scene', 'celebration-photo', 'instagram-source'],
            ['argentina-era', 'copa-america', 'season-2023-2024', 'year-2024', 'award-scene', 'group-photo', 'x-source'],
            ['barcelona-era', 'club-friendly', 'season-2023-2024', 'year-2023', 'match-scene', 'match-photo', 'local-test-source'],
            ['inter-miami-era', 'club-friendly', 'season-2024-2025', 'year-2025', 'training-scene', 'portrait-photo', 'instagram-source'],
        ][$index % 4];

        return [
            $categories['career-stage'][$rotation[0]]->id,
            $categories['competition'][$rotation[1]]->id,
            $categories['season'][$rotation[2]]->id,
            $categories['year'][$rotation[3]]->id,
            $categories['scene'][$rotation[4]]->id,
            $categories['image-type'][$rotation[5]]->id,
            $categories['source-platform'][$rotation[6]]->id,
        ];
    }

    /**
     * @param  array<string, array<string, Category>>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function albumPlans(array $categories): array
    {
        return [
            [
                'title' => '测试相册：世界杯冠军时刻',
                'slug' => 'demo-world-cup-moments',
                'description' => '用于验证相册列表、相册详情和首页专题入口的本地测试相册。',
                'categories' => [
                    $categories['career-stage']['argentina-era']->id,
                    $categories['competition']['world-cup']->id,
                    $categories['season']['season-2022-2023']->id,
                    $categories['year']['year-2022']->id,
                    $categories['scene']['match-scene']->id,
                    $categories['image-type']['celebration-photo']->id,
                    $categories['source-platform']['local-test-source']->id,
                ],
            ],
            [
                'title' => '测试相册：阿根廷国家队',
                'slug' => 'demo-argentina-team',
                'description' => '用于验证国家队主题图片聚合和公开图片筛选。',
                'categories' => [
                    $categories['career-stage']['argentina-era']->id,
                    $categories['competition']['copa-america']->id,
                    $categories['season']['season-2023-2024']->id,
                    $categories['year']['year-2024']->id,
                    $categories['scene']['award-scene']->id,
                    $categories['image-type']['group-photo']->id,
                    $categories['source-platform']['instagram-source']->id,
                ],
            ],
            [
                'title' => '测试相册：巴萨经典回忆',
                'slug' => 'demo-barcelona-classics',
                'description' => '用于验证俱乐部历史类相册的前台展示。',
                'categories' => [
                    $categories['career-stage']['barcelona-era']->id,
                    $categories['competition']['club-friendly']->id,
                    $categories['season']['season-2023-2024']->id,
                    $categories['year']['year-2023']->id,
                    $categories['scene']['match-scene']->id,
                    $categories['image-type']['match-photo']->id,
                    $categories['source-platform']['x-source']->id,
                ],
            ],
            [
                'title' => '测试相册：迈阿密训练日',
                'slug' => 'demo-miami-training',
                'description' => '用于验证训练、肖像和最新照片模块的本地测试相册。',
                'categories' => [
                    $categories['career-stage']['inter-miami-era']->id,
                    $categories['competition']['club-friendly']->id,
                    $categories['season']['season-2024-2025']->id,
                    $categories['year']['year-2025']->id,
                    $categories['scene']['training-scene']->id,
                    $categories['image-type']['portrait-photo']->id,
                    $categories['source-platform']['local-test-source']->id,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function upsertAlbum(array $plan, $photos): Album
    {
        $album = Album::query()->updateOrCreate(
            ['slug' => $plan['slug']],
            [
                'title' => $plan['title'],
                'description' => $plan['description'],
                'cover_photo_id' => $photos->first()?->id,
                'sort_order' => 10,
                'status' => 'published',
                'published_at' => now()->subDay(),
            ],
        );

        $album->categories()->sync($plan['categories']);
        $album->photos()->sync($photos->pluck('id')->all());

        return $album;
    }

    /**
     * @return array<int, User>
     */
    private function demoUsers(): array
    {
        $plans = [
            ['name' => '影像考古员 Leo', 'email' => 'demo-leo@example.test'],
            ['name' => '世界杯记忆收藏者', 'email' => 'demo-worldcup@example.test'],
            ['name' => '巴萨老照片整理员', 'email' => 'demo-barca@example.test'],
            ['name' => '迈阿密训练观察员', 'email' => 'demo-miami@example.test'],
        ];

        return collect($plans)
            ->map(fn (array $plan): User => User::query()->updateOrCreate(
                ['email' => $plan['email']],
                [
                    'name' => $plan['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            ))
            ->all();
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedDemoComments($photos, array $users): int
    {
        if ($photos->isEmpty() || $users === []) {
            return 0;
        }

        $publishedComments = [
            '这张测试图适合检查评论区的行距和长文本换行。',
            '这里模拟一条和赛事背景相关的公开讨论。',
            '这条评论用于观察不同用户昵称在前台的展示效果。',
            '如果后续补上审核后台，这类内容应该先经过审核再公开。',
        ];
        $corrections = [
            ['field' => 'event_date', 'value' => '2022-12-18', 'content' => '建议核对事件日期，可能对应世界杯决赛。'],
            ['field' => 'source', 'value' => '官方社媒或赛事图集', 'content' => '建议补充更明确的原始来源链接。'],
            ['field' => 'people', 'value' => '队友同框', 'content' => '画面里可能还有其他队友，需要后续确认。'],
        ];
        $count = 0;

        foreach ($photos->take(12)->values() as $index => $photo) {
            $user = $users[$index % count($users)];
            $content = $publishedComments[$index % count($publishedComments)];

            Comment::query()->updateOrCreate(
                [
                    'photo_id' => $photo->id,
                    'user_id' => $user->id,
                    'type' => 'discussion',
                    'content' => $content,
                ],
                [
                    'status' => 'published',
                    'created_at' => now()->subMinutes(60 - $index),
                    'updated_at' => now()->subMinutes(60 - $index),
                ],
            );
            $count++;

            if ($index < count($corrections)) {
                $correction = $corrections[$index];
                Comment::query()->updateOrCreate(
                    [
                        'photo_id' => $photo->id,
                        'user_id' => $user->id,
                        'type' => 'correction',
                        'content' => $correction['content'],
                    ],
                    [
                        'status' => 'pending',
                        'correction_field' => $correction['field'],
                        'suggested_value' => $correction['value'],
                        'evidence_url' => 'https://example.com/demo-evidence',
                    ],
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedDemoInteractions($photos, array $users): int
    {
        if ($photos->isEmpty() || $users === []) {
            return 0;
        }

        $count = 0;

        foreach ($photos->take(18)->values() as $index => $photo) {
            $likeUsers = collect($users)->take(($index % count($users)) + 1);
            $favoriteUsers = collect($users)->take((($index + 1) % count($users)) + 1);
            $shareUsers = collect($users)->take(($index % 3) + 1);

            foreach ($likeUsers as $user) {
                PhotoLike::query()->firstOrCreate([
                    'photo_id' => $photo->id,
                    'user_id' => $user->id,
                ], [
                    'created_at' => now()->subHours($index + 1),
                    'updated_at' => now()->subHours($index + 1),
                ]);
                $count++;
            }

            foreach ($favoriteUsers as $user) {
                PhotoFavorite::query()->firstOrCreate([
                    'photo_id' => $photo->id,
                    'user_id' => $user->id,
                ], [
                    'created_at' => now()->subHours($index + 2),
                    'updated_at' => now()->subHours($index + 2),
                ]);
                $count++;
            }

            foreach ($shareUsers as $user) {
                PhotoShare::query()->updateOrCreate([
                    'photo_id' => $photo->id,
                    'user_id' => $user->id,
                    'channel' => 'copy_link',
                    'page_url' => url('/photos/'.$photo->uuid),
                ], [
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'LocalGalleryDemoSeeder',
                    'created_at' => now()->subHours($index + 3),
                    'updated_at' => now()->subHours($index + 3),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedDemoSponsorships(array $users): int
    {
        if ($users === []) {
            return 0;
        }

        $plans = SponsorshipPlan::query()->active()->orderBy('sort_order')->get()->values();

        if ($plans->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($users as $index => $user) {
            $plan = $plans[$index % $plans->count()];
            $order = SponsorshipOrder::query()->firstOrCreate(
                ['order_no' => 'SPDEMO'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'sponsorship_plan_id' => $plan->id,
                    'amount_cents' => $plan->amount_cents,
                    'channel' => 'mock',
                    'status' => 'pending',
                    'created_at' => now()->subDays($index + 1),
                    'updated_at' => now()->subDays($index + 1),
                ],
            );

            app(SponsorshipService::class)->markPaid($order, 'mock', null, '本地演示数据：模拟赞助已支付。');

            $profile = $user->refresh()->supporterProfile;
            if ($profile !== null && $index === count($users) - 1) {
                $profile->update(['show_publicly' => false]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedDemoBadges(array $users): int
    {
        if ($users === []) {
            return 0;
        }

        $service = app(BadgeService::class);

        foreach ($users as $user) {
            $service->evaluate($user->refresh());
        }

        $manualBadge = Badge::query()->where('slug', 'curator-pick')->first();

        if ($manualBadge instanceof Badge) {
            $service->award($users[0]->refresh(), $manualBadge, 'manual', null, '本地演示数据：人工精选贡献。');
        }

        return collect($users)->sum(fn (User $user): int => $user->userBadges()->where('status', 'earned')->count());
    }

    private function seedDemoProcessing($photos): int
    {
        if ($photos->isEmpty()) {
            return 0;
        }

        $service = app(PhotoProcessingService::class);
        $count = 0;

        foreach ($photos as $photo) {
            $photo->processingJobs()->delete();
            $photo->analysisResult()->delete();

            foreach ($service->createDefaultJobs($photo) as $job) {
                $result = $service->process($job->refresh());
                if ($result->status === 'done') {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function saveHomepageSettings($photos, $albums): void
    {
        $defaults = HomepageSettings::defaults();
        $heroPhotos = $photos->take(3)->values();
        $topicAlbums = $albums->take(4)->values();

        Setting::setValue('site', 'basic', array_replace_recursive($defaults['site'], [
            'search_placeholder' => '搜索测试图片、相册、赛事或年份',
        ]), null, '站点基础信息');

        $navigation = $defaults['navigation'];
        foreach ($navigation['items'] as &$item) {
            if (in_array($item['url'] ?? null, ['/timeline', '/rankings', '/support'], true)) {
                $item['enabled'] = true;
            }
        }
        unset($item);

        Setting::setValue('site', 'navigation', $navigation, null, '首页导航配置');

        Setting::setValue('home', 'hero_slides', $heroPhotos->map(fn (Photo $photo, int $index): array => [
            'title' => ['梅西影像测试首页', '冠军瞬间测试头图', '训练与赛场测试头图'][$index] ?? '梅西影像测试头图',
            'subtitle' => '本地测试素材，用于验收首页头图和整体视觉效果。',
            'button_label' => $index === 0 ? '浏览图库' : '查看图片',
            'button_url' => $index === 0 ? '/photos' : '/photos/'.$photo->uuid,
            'desktop_image_path' => $photo->display_key,
            'mobile_image_path' => $photo->display_key,
            'enabled' => true,
        ])->all(), null, '首页头图轮播');

        Setting::setValue('home', 'latest_photos', array_replace_recursive($defaults['latest_photos'], [
            'display_count' => 15,
            'pinned_photo_ids' => $photos->take(6)->pluck('id')->all(),
        ]), null, '首页最新照片模块');

        Setting::setValue('home', 'topic_module', array_replace_recursive($defaults['topic_module'], [
            'items' => $topicAlbums->map(fn (Album $album): array => [
                'title' => Str::after($album->title, '测试相册：'),
                'url' => '/topics/'.Str::after($album->slug, 'demo-'),
                'cover_image_path' => optional($photos->firstWhere('id', $album->cover_photo_id))->display_key,
                'enabled' => true,
                'description' => $album->description,
                'album_ids' => [$album->id],
                'photo_ids' => $album->photos()->published()->limit(5)->pluck('photos.id')->all(),
            ])->all(),
        ]), null, '首页专题模块');

        Setting::setValue('site', 'footer', array_replace_recursive($defaults['footer'], [
            'copyright_text' => '梅西影像档案库 - 本地测试数据',
        ]), null, '页脚配置');
    }
}
