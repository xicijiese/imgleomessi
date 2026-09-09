<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class GalleryTaxonomySeeder extends Seeder
{
    /**
     * 当前图库使用的主分类和子分类。子分类 slug 保持稳定，便于重复执行 Seeder。
     *
     * @var array<string, array<int, array{slug: string, name: string}>>
     */
    public const CHILDREN = [
        'career-stage' => [
            ['slug' => 'newells-old-boys', 'name' => '纽维尔老男孩'],
            ['slug' => 'youth-period', 'name' => '少年时期'],
            ['slug' => 'barcelona', 'name' => '巴萨'],
            ['slug' => 'paris-saint-germain', 'name' => '巴黎'],
            ['slug' => 'inter-miami', 'name' => '迈阿密国际'],
            ['slug' => 'argentina-national-team', 'name' => '阿根廷国家队'],
            ['slug' => 'argentina-youth-team', 'name' => '阿根廷国青队'],
            ['slug' => 'argentina-olympic-team', 'name' => '阿根廷国奥队'],
            ['slug' => 'post-retirement', 'name' => '退役后'],
        ],
        'competition' => [
            ['slug' => 'la-liga', 'name' => '西甲'],
            ['slug' => 'uefa-champions-league', 'name' => '欧冠'],
            ['slug' => 'copa-del-rey', 'name' => '国王杯'],
            ['slug' => 'spanish-super-cup', 'name' => '西超杯'],
            ['slug' => 'fifa-club-world-cup', 'name' => '世俱杯'],
            ['slug' => 'uefa-super-cup', 'name' => '欧超杯'],
            ['slug' => 'ligue-1', 'name' => '法甲'],
            ['slug' => 'coupe-de-france', 'name' => '法国杯'],
            ['slug' => 'trophee-des-champions', 'name' => '法超杯'],
            ['slug' => 'fifa-world-cup', 'name' => '世界杯'],
            ['slug' => 'copa-america', 'name' => '美洲杯'],
            ['slug' => 'friendly-match', 'name' => '友谊赛'],
            ['slug' => 'world-cup-qualifiers', 'name' => '世预赛'],
            ['slug' => 'olympics', 'name' => '奥运会'],
            ['slug' => 'fifa-u20-world-cup', 'name' => '世青赛'],
            ['slug' => 'finalissima', 'name' => '欧美杯'],
            ['slug' => 'mls', 'name' => '美职联'],
            ['slug' => 'leagues-cup', 'name' => '北美联赛杯'],
            ['slug' => 'us-open-cup', 'name' => '美公开杯'],
            ['slug' => 'concacaf-champions-cup', 'name' => '中北美冠军杯'],
            ['slug' => 'campeones-cup', 'name' => '美墨超级杯'],
        ],
        'season' => [
            ['slug' => 'season-04-05', 'name' => '04/05赛季'],
            ['slug' => 'season-05-06', 'name' => '05/06赛季'],
            ['slug' => 'season-06-07', 'name' => '06/07赛季'],
            ['slug' => 'season-07-08', 'name' => '07/08赛季'],
            ['slug' => 'season-08-09', 'name' => '08/09赛季'],
            ['slug' => 'season-09-10', 'name' => '09/10赛季'],
            ['slug' => 'season-10-11', 'name' => '10/11赛季'],
            ['slug' => 'season-11-12', 'name' => '11/12赛季'],
            ['slug' => 'season-12-13', 'name' => '12/13赛季'],
            ['slug' => 'season-13-14', 'name' => '13/14赛季'],
            ['slug' => 'season-14-15', 'name' => '14/15赛季'],
            ['slug' => 'season-15-16', 'name' => '15/16赛季'],
            ['slug' => 'season-16-17', 'name' => '16/17赛季'],
            ['slug' => 'season-17-18', 'name' => '17/18赛季'],
            ['slug' => 'season-18-19', 'name' => '18/19赛季'],
            ['slug' => 'season-19-20', 'name' => '19/20赛季'],
            ['slug' => 'season-20-21', 'name' => '20/21赛季'],
            ['slug' => 'season-21-22', 'name' => '21/22赛季'],
            ['slug' => 'season-22-23', 'name' => '22/23赛季'],
            ['slug' => 'season-2023', 'name' => '2023赛季'],
            ['slug' => 'season-2024', 'name' => '2024赛季'],
            ['slug' => 'season-2025', 'name' => '2025赛季'],
            ['slug' => 'season-2026', 'name' => '2026赛季'],
        ],
        'year' => [
            ['slug' => 'before-2003', 'name' => '2003年之前'],
            ['slug' => 'year-2003', 'name' => '2003年'],
            ['slug' => 'year-2004', 'name' => '2004年'],
            ['slug' => 'year-2005', 'name' => '2005年'],
            ['slug' => 'year-2006', 'name' => '2006年'],
            ['slug' => 'year-2007', 'name' => '2007年'],
            ['slug' => 'year-2008', 'name' => '2008年'],
            ['slug' => 'year-2009', 'name' => '2009年'],
            ['slug' => 'year-2010', 'name' => '2010年'],
            ['slug' => 'year-2011', 'name' => '2011年'],
            ['slug' => 'year-2012', 'name' => '2012年'],
            ['slug' => 'year-2013', 'name' => '2013年'],
            ['slug' => 'year-2014', 'name' => '2014年'],
            ['slug' => 'year-2015', 'name' => '2015年'],
            ['slug' => 'year-2016', 'name' => '2016年'],
            ['slug' => 'year-2017', 'name' => '2017年'],
            ['slug' => 'year-2018', 'name' => '2018年'],
            ['slug' => 'year-2019', 'name' => '2019年'],
            ['slug' => 'year-2020', 'name' => '2020年'],
            ['slug' => 'year-2021', 'name' => '2021年'],
            ['slug' => 'year-2022', 'name' => '2022年'],
            ['slug' => 'year-2023', 'name' => '2023年'],
            ['slug' => 'year-2024', 'name' => '2024年'],
            ['slug' => 'year-2025', 'name' => '2025年'],
            ['slug' => 'year-2026', 'name' => '2026年'],
            ['slug' => 'year-2027', 'name' => '2027年'],
            ['slug' => 'year-2028', 'name' => '2028年'],
            ['slug' => 'after-2028', 'name' => '2028年之后'],
        ],
        'scene' => [
            ['slug' => 'match', 'name' => '比赛'],
            ['slug' => 'training', 'name' => '训练'],
            ['slug' => 'press-conference', 'name' => '发布会'],
            ['slug' => 'locker-room', 'name' => '更衣室'],
            ['slug' => 'award-ceremony', 'name' => '颁奖典礼'],
            ['slug' => 'parade-celebration', 'name' => '游行庆典'],
            ['slug' => 'fan-interaction', 'name' => '球迷互动'],
            ['slug' => 'commercial-event', 'name' => '商业活动'],
            ['slug' => 'family-life', 'name' => '家庭生活'],
            ['slug' => 'social-media-photo', 'name' => '社媒照片'],
            ['slug' => 'fan-street-photo', 'name' => '球迷街拍'],
            ['slug' => 'advertising-portrait', 'name' => '广告写真'],
        ],
        'image-type' => [
            ['slug' => 'match-photo', 'name' => '比赛图'],
            ['slug' => 'training-photo', 'name' => '训练图'],
            ['slug' => 'celebration-photo', 'name' => '庆祝图'],
            ['slug' => 'award-photo', 'name' => '领奖图'],
            ['slug' => 'group-photo', 'name' => '合照'],
            ['slug' => 'solo-photo', 'name' => '单人照'],
            ['slug' => 'wallpaper', 'name' => '壁纸'],
            ['slug' => 'poster', 'name' => '海报'],
            ['slug' => 'screenshot', 'name' => '截图'],
            ['slug' => 'news-photo', 'name' => '新闻图'],
            ['slug' => 'social-media-image', 'name' => '社媒图'],
        ],
        'person-relation' => [
            ['slug' => 'teammate', 'name' => '队友'],
            ['slug' => 'coach', 'name' => '教练'],
            ['slug' => 'family', 'name' => '家人'],
            ['slug' => 'opponent', 'name' => '对手'],
            ['slug' => 'celebrity-collaboration', 'name' => '名人合影'],
        ],
        'source-platform' => [
            ['slug' => 'official', 'name' => '官方'],
            ['slug' => 'media', 'name' => '媒体'],
            ['slug' => 'social-media', 'name' => '社媒'],
            ['slug' => 'fan-submission', 'name' => '球迷投稿'],
            ['slug' => 'screenshot-source', 'name' => '截图'],
            ['slug' => 'unknown-source', 'name' => '未知来源'],
        ],
    ];

    /**
     * 语义等价的旧演示分类原地迁移，保留已有图片和相册关联。
     *
     * @var array<string, array<string, array{slug: string, name: string}>>
     */
    private const LEGACY_RENAMES = [
        'career-stage' => [
            'argentina-era' => ['slug' => 'argentina-national-team', 'name' => '阿根廷国家队'],
            'barcelona-era' => ['slug' => 'barcelona', 'name' => '巴萨'],
            'inter-miami-era' => ['slug' => 'inter-miami', 'name' => '迈阿密国际'],
        ],
        'season' => [
            'season-2022-2023' => ['slug' => 'season-22-23', 'name' => '22/23赛季'],
            'season-2023-2024' => ['slug' => 'season-2023', 'name' => '2023赛季'],
            'season-2024-2025' => ['slug' => 'season-2024', 'name' => '2024赛季'],
        ],
        'year' => [
            'year-2022' => ['slug' => 'year-2022', 'name' => '2022年'],
            'year-2023' => ['slug' => 'year-2023', 'name' => '2023年'],
            'year-2024' => ['slug' => 'year-2024', 'name' => '2024年'],
            'year-2025' => ['slug' => 'year-2025', 'name' => '2025年'],
        ],
        'scene' => [
            'match-scene' => ['slug' => 'match', 'name' => '比赛'],
            'training-scene' => ['slug' => 'training', 'name' => '训练'],
            'award-scene' => ['slug' => 'award-ceremony', 'name' => '颁奖典礼'],
        ],
        'image-type' => [
            'portrait-photo' => ['slug' => 'solo-photo', 'name' => '单人照'],
            'group-photo' => ['slug' => 'group-photo', 'name' => '合照'],
        ],
    ];

    public function run(): void
    {
        $sortOrder = 10;

        foreach (Category::ROOT_CATEGORIES as $slug => $name) {
            $root = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'parent_id' => null,
                    'name' => $name,
                    'description' => in_array($slug, Category::REQUIRED_ROOT_SLUGS, true)
                        ? '发布时必填的主分类；子分类可由管理员继续维护。'
                        : '可选资料维度；子分类可由管理员继续维护。',
                    'sort_order' => $sortOrder,
                    'visibility' => 'public',
                    'is_system' => true,
                    'required_for_publish' => in_array($slug, Category::REQUIRED_ROOT_SLUGS, true),
                ],
            );

            $this->renameLegacyChildren($root, $slug);

            foreach (self::CHILDREN[$slug] ?? [] as $index => $child) {
                Category::updateOrCreate(
                    ['parent_id' => $root->id, 'slug' => $child['slug']],
                    [
                        'name' => $child['name'],
                        'description' => $name.'：'.$child['name'].'。',
                        'sort_order' => ($index + 1) * 10,
                        'visibility' => 'public',
                        'is_system' => true,
                    ],
                );
            }

            Category::updateOrCreate(
                ['slug' => 'pending-'.$slug],
                [
                    'parent_id' => $root->id,
                    'name' => '待补充',
                    'description' => $name.'信息待补充，后续由管理员或编辑根据可靠线索修正。',
                    'sort_order' => 999,
                    'visibility' => 'public',
                    'is_system' => true,
                ],
            );

            $sortOrder += 10;
        }
    }

private function renameLegacyChildren(Category $root, string $rootSlug): void
    {
        foreach (self::LEGACY_RENAMES[$rootSlug] ?? [] as $legacySlug => $target) {
            $legacy = Category::query()
                ->where('parent_id', $root->id)
                ->where('slug', $legacySlug)
                ->first();

            if (! $legacy) {
                continue;
            }

            $targetCategory = Category::query()
                ->where('parent_id', $root->id)
                ->where('slug', $target['slug'])
                ->whereKeyNot($legacy->id)
                ->first();

            if (! $targetCategory) {
                $legacy->update([
                    'slug' => $target['slug'],
                    'name' => $target['name'],
                ]);

                continue;
            }

            $photoIds = $legacy->photos()->pluck('photos.id')->all();
            $albumIds = $legacy->albums()->pluck('albums.id')->all();
            $targetCategory->photos()->syncWithoutDetaching($photoIds);
            $targetCategory->albums()->syncWithoutDetaching($albumIds);
            $legacy->photos()->detach();
            $legacy->albums()->detach();
            $legacy->delete();
        }
    }
}