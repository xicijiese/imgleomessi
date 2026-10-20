<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Services\BadgeService;
use App\Services\UserCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserBadgeController extends Controller
{
    public function __construct(
        private readonly BadgeService $badges,
        private readonly UserCenter $center,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Me/Badges', [
            'me' => $this->badges->userCenterPage($request->user(), $this->center->shell($request->user())),
        ]);
    }

    public function hall(Request $request): Response
    {
        return Inertia::render('Me/BadgeHall', [
            'hall' => [
                ...$this->center->shell($request->user()),
                'badges' => Badge::query()->active()->orderBy('sort_order')->orderBy('id')->get()->map(fn (Badge $badge): array => [
                    'id' => $badge->id, 'name' => $badge->name, 'description' => $badge->description,
                    'image_url' => $badge->image_url, 'icon_key' => $badge->icon_key, 'color' => $badge->color,
                    'rule_label' => $badge->rule_label, 'availability' => $this->badges->availability($badge),
                ]),
            ],
        ]);
    }

    public function equip(Request $request, Badge $badge): RedirectResponse
    {
        abort_unless(($request->user()->status ?? 'active') === 'active' && ! $request->user()->isBanned(), 403);
        $data = $request->validate(['equipped' => ['sometimes', 'boolean']]);
        $this->badges->equip($request->user(), $badge, $data['equipped'] ?? true);

        return back(status: 303)->with('status', 'badge-equipped');
    }
}
