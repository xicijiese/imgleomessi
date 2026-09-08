<?php

namespace App\Http\Controllers;

use App\Models\SupporterProfile;
use App\Services\PublicSponsorship;
use App\Services\UserCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserSponsorshipController extends Controller
{
    public function index(Request $request, UserCenter $center, PublicSponsorship $sponsorship): Response
    {
        return Inertia::render('Me/Sponsorships', [
            'me' => $sponsorship->userCenter($request->user(), $center->shell($request->user())),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->sponsorshipOrders()->paid()->exists() || $user->supporterProfile()->exists(), 403);

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:80'],
            'show_publicly' => ['required', 'boolean'],
        ]);

        SupporterProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => blank($data['display_name'] ?? null) ? null : $data['display_name'],
                'show_publicly' => (bool) $data['show_publicly'],
            ],
        );

        return back()->with('status', 'supporter-profile-updated');
    }
}
