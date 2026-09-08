<?php

namespace App\Http\Controllers;

use App\Services\UserCenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserReportController extends Controller
{
    public function index(Request $request, UserCenter $center): Response
    {
        return Inertia::render('Me/Reports', [
            'me' => $center->reports($request->user(), $request->only(['status'])),
        ]);
    }
}
