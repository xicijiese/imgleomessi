<?php

namespace App\Http\Controllers;

use App\Models\PhotoFavorite;
use App\Services\UserCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserFavoriteController extends Controller
{
    public function index(Request $request, UserCenter $center): Response
    {
        return Inertia::render('Me/Favorites', [
            'me' => $center->favorites($request->user(), $request->only(['q', 'category_id', 'date_from', 'date_to'])),
        ]);
    }

    public function destroy(Request $request, PhotoFavorite $favorite): JsonResponse|RedirectResponse
    {
        abort_unless($favorite->user_id === $request->user()->id, 404);

        $favorite->delete();

        if ($request->wantsJson()) {
            return response()->json(['removed' => true]);
        }

        return back(status: 303);
    }
}
