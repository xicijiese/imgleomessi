<?php

namespace App\Http\Controllers;

use App\Services\UserCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserCenterController extends Controller
{
    public function show(Request $request, UserCenter $center): Response
    {
        return Inertia::render('Me/Show', [
            'me' => $center->overview($request->user()),
        ]);
    }

    public function updatePublicProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_public' => ['required', 'boolean'],
            'profile_bio' => ['nullable', 'string', 'max:240'],
        ]);

        $request->user()->update([
            'profile_public' => (bool) $validated['profile_public'],
            'profile_bio' => filled($validated['profile_bio'] ?? null) ? $validated['profile_bio'] : null,
        ]);

        return redirect()->route('me.show', [], 303)->with('status', 'public-profile-updated');
    }
}
