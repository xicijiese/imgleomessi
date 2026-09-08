<?php

namespace App\Http\Controllers;

use App\Services\PublicPhotoGallery;
use App\Services\PublicSearchOperations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request, PublicPhotoGallery $gallery, PublicSearchOperations $operations): Response
    {
        $search = $gallery->payload($request);
        $operations->record($request, $search);

        return Inertia::render('Search/Index', [
            'search' => $search,
            'search_operations' => $operations->pagePayload(),
        ]);
    }
}