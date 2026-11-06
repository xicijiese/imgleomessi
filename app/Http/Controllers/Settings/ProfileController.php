<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\PublicHomepage;
use App\Services\UserAvatarService;
use App\Services\UserCenter;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request, PublicHomepage $homepage, UserCenter $center): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'settingsShell' => $homepage->shell(),
            'settingsCenter' => $center->shell($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, UserAvatarService $avatars): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $avatar = $validated['avatar'] ?? null;
        $removeAvatar = (bool) ($validated['remove_avatar'] ?? false);
        unset($validated['avatar'], $validated['remove_avatar']);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($avatar instanceof UploadedFile) {
            $avatars->replace($user, $avatar);
        } elseif ($removeAvatar) {
            $avatars->remove($user);
        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
