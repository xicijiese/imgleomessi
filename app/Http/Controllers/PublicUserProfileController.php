<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PublicUserProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicUserProfileController extends Controller
{
    public function show(Request $request, User $user, PublicUserProfile $profiles): Response
    {
        return Inertia::render('Users/Show', [
            'profilePage' => $profiles->show($user, $request->user()),
        ]);
    }
}
