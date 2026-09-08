<?php

namespace App\Http\Controllers;

use App\Services\UserCenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserCommentController extends Controller
{
    public function index(Request $request, UserCenter $center): Response
    {
        return Inertia::render('Me/Comments', [
            'me' => $center->comments($request->user(), $request->only(['type', 'status'])),
        ]);
    }
}
