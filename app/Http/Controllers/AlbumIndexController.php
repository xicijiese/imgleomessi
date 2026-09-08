<?php

namespace App\Http\Controllers;

use App\Services\PublicAlbumIndex;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlbumIndexController extends Controller
{
    public function __invoke(Request $request, PublicAlbumIndex $albums): Response
    {
        return Inertia::render('Albums/Index', [
            'albumIndex' => $albums->payload($request),
        ]);
    }
}
