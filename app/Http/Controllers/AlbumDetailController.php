<?php

namespace App\Http\Controllers;

use App\Services\PublicAlbumDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlbumDetailController extends Controller
{
    public function __invoke(Request $request, string $slug, PublicAlbumDetail $albums): Response
    {
        return Inertia::render('Albums/Show', [
            'albumDetail' => $albums->payload($request, $slug),
        ]);
    }
}
