<?php

namespace App\Http\Controllers;

use App\Services\PublicPhotoGallery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhotoGalleryController extends Controller
{
    public function __invoke(Request $request, PublicPhotoGallery $gallery): Response
    {
        return Inertia::render('Photos/Index', [
            'gallery' => $gallery->payload($request),
        ]);
    }
}
