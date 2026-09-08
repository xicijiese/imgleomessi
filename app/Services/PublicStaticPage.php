<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicStaticPage
{
    public function __construct(private readonly HomepageSettings $settings, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(string $key): array
    {
        $settings = $this->settings->formState();
        $page = $this->pages($settings)[$key] ?? null;

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return [
            'site' => $this->site($settings),
            'navigation' => $this->navigation($settings),
            'seo' => $this->seo->staticPage($page),
            'footer' => $this->footer($settings),
            'page' => $page,
            'page_links' => $this->pageLinks(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function site(array $settings): array
    {
        $site = Arr::get($settings, 'site', []);

        return [
            'name' => $site['name'] ?? '梅西影像档案库',
            'logo_url' => PublicMediaUrl::fromPublicDisk($site['logo_path'] ?? null),
            'search_placeholder' => $site['search_placeholder'] ?? '搜索图片、相册、赛事或年份',
            'contact_email' => filled($site['contact_email'] ?? null) ? (string) $site['contact_email'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{label: string, url: string}>
     */
    private function navigation(array $settings): array
    {
        return collect(Arr::get($settings, 'navigation.items', []))
            ->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? false))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
            ])
            ->filter(fn (array $item): bool => filled($item['label']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function footer(array $settings): array
    {
        $footer = Arr::get($settings, 'footer', []);

        return [
            'copyright_text' => $footer['copyright_text'] ?? '梅西影像档案库',
            'icp_text' => $footer['icp_text'] ?? null,
            'links' => $this->withStaticPageLinks($this->enabledLinks($footer['links'] ?? [])),
            'social_links' => $this->enabledLinks($footer['social_links'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, url: string}>
     */
    private function enabledLinks(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? false))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? $item['platform'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
            ])
            ->filter(fn (array $item): bool => filled($item['label']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{label: string, url: string}>  $links
     * @return array<int, array{label: string, url: string}>
     */
    private function withStaticPageLinks(array $links): array
    {
        $existingUrls = collect($links)->pluck('url')->all();

        return collect($links)
            ->concat(collect($this->pageLinks())->reject(fn (array $link): bool => in_array($link['url'], $existingUrls, true)))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function pageLinks(): array
    {
        return [
            ['label' => '关于本站', 'url' => '/about'],
            ['label' => '版权说明', 'url' => '/copyright'],
            ['label' => '下架申请', 'url' => '/takedown'],
            ['label' => '隐私政策', 'url' => '/privacy'],
            ['label' => '用户协议', 'url' => '/terms'],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    private function pages(array $settings): array
    {
        $contactEmail = Arr::get($settings, 'site.contact_email');

        return [
            'about' => [
                'key' => 'about',
                'title' => '关于本站',
                'eyebrow' => 'About',
                'description' => '梅西影像档案库是一个面向中文用户的图片资料整理站，核心目标是让图片可以被按时间、赛事、球队、相册、标签和来源长期查阅。',
                'notice' => '本站不是梅西官方站，不代表梅西本人、俱乐部、国家队或任何官方机构。',
                'sections' => [
                    [
                        'title' => '我们整理什么',
                        'items' => [
                            '公开网络中已经传播的梅西相关图片资料。',
                            '围绕图片补充事件时间、相册、分类、标签和来源线索。',
                            '优先服务资料查阅、考古整理和中文球迷内容创作。',
                        ],
                    ],
                    [
                        'title' => '我们不做什么',
                        'items' => [
                            '不宣称本站为官方图库或授权图库。',
                            '不售卖图片版权，不做付费解锁原图。',
                            '不把赞助支持解释为购买图片或版权授权。',
                        ],
                    ],
                ],
            ],
            'copyright' => [
                'key' => 'copyright',
                'title' => '版权说明',
                'eyebrow' => 'Copyright',
                'description' => '本站展示内容用于资料整理、索引和考古参考，图片版权归原权利人所有。',
                'notice' => '任何页面都不应被理解为图片版权转让、授权销售或官方背书。',
                'sections' => [
                    [
                        'title' => '版权边界',
                        'items' => [
                            '图片版权、肖像权、商标权和赛事相关权利归对应权利人所有。',
                            '本站只记录必要来源和版权备注，方便后续追溯、纠错和下架处理。',
                            '如某张图片不适合继续展示，权利人可以通过下架申请入口联系处理。',
                        ],
                    ],
                    [
                        'title' => '使用提醒',
                        'items' => [
                            '用户不应将本站图片用于商业售卖、误导性宣传或侵犯第三方权益的场景。',
                            '引用本站资料时，请优先回到原始来源核对版权和使用条件。',
                        ],
                    ],
                ],
            ],
            'takedown' => [
                'key' => 'takedown',
                'title' => '下架申请',
                'eyebrow' => 'Takedown',
                'description' => '如果你是权利人或获得授权的代理人，并认为本站某张图片不适合展示，可以提交下架或修正请求。',
                'notice' => 'P1-3 先提供处理说明和联系入口，正式表单和工单系统后续单独确认。',
                'contact_email' => filled($contactEmail) ? (string) $contactEmail : null,
                'sections' => [
                    [
                        'title' => '请尽量提供',
                        'items' => [
                            '需要处理的页面链接或图片标题。',
                            '你与图片权利的关系说明。',
                            '希望处理的方式：下架、补充来源、修正版权备注或其他说明。',
                        ],
                    ],
                    [
                        'title' => '处理原则',
                        'items' => [
                            '收到清晰信息后，管理员会优先核对并临时隐藏高风险内容。',
                            '明显错误的来源、日期或版权备注会按资料规则修正。',
                            '无法判断权属时，会优先降低公开展示风险。',
                        ],
                    ],
                ],
            ],
            'privacy' => [
                'key' => 'privacy',
                'title' => '隐私政策',
                'eyebrow' => 'Privacy',
                'description' => '本站仅收集维持账号登录、内容互动和基础安全所需的信息。',
                'notice' => 'P1 当前账号能力以注册、登录和基础身份入口为主，收藏、评论、赞助和通知等数据会在对应切片确认后再扩展。',
                'sections' => [
                    [
                        'title' => '可能收集的信息',
                        'items' => [
                            '账号注册时填写的昵称、邮箱和加密后的密码。',
                            '登录状态、会话 Cookie 和基础安全日志。',
                            '后续收藏、评论、赞助、通知等功能产生的数据，会在功能上线前补充边界。',
                        ],
                    ],
                    [
                        'title' => '使用方式',
                        'items' => [
                            '用于账号登录、身份识别、安全风控和必要的站点维护。',
                            '不会把用户账号信息包装成图片版权交易或出售给第三方。',
                        ],
                    ],
                ],
            ],
            'terms' => [
                'key' => 'terms',
                'title' => '用户协议',
                'eyebrow' => 'Terms',
                'description' => '使用本站代表你理解本站的资料整理定位，并同意不把本站内容用于侵犯他人权益的用途。',
                'notice' => '本站会逐步加入评论、收藏、赞助等能力，对应规则会随功能切片继续补充。',
                'sections' => [
                    [
                        'title' => '用户责任',
                        'items' => [
                            '不得冒充官方机构、权利人或其他用户。',
                            '不得利用本站内容进行图片版权售卖、诈骗或误导性宣传。',
                            '后续评论、纠错和投稿功能上线后，不得提交违法、侵权、骚扰或恶意内容。',
                        ],
                    ],
                    [
                        'title' => '站点处理权',
                        'items' => [
                            '管理员可以隐藏、修正或删除存在版权风险、资料错误或安全风险的内容。',
                            '违反规则的账号可能被限制互动能力；具体封禁和申诉流程会在后续审核切片中确认。',
                        ],
                    ],
                ],
            ],
        ];
    }
}
