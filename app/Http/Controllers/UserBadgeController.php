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

    public function equip(Request $request, Badge $badge): RedirectResponse
    {
        abort_unless($badge->is_active, 404);

        $this->badges->equip($request->user(), $badge);

        return back(status: 303)->with('status', 'badge-equipped');
    }
}
