<?php

namespace App\Http\Controllers;

use App\Services\PublicStaticPage;
use Inertia\Inertia;
use Inertia\Response;

class StaticPageController extends Controller
{
    public function __construct(private readonly PublicStaticPage $pages) {}

    public function about(): Response
    {
        return $this->render('about');
    }

    public function copyright(): Response
    {
        return $this->render('copyright');
    }

    public function takedown(): Response
    {
        return $this->render('takedown');
    }

    public function privacy(): Response
    {
        return $this->render('privacy');
    }

    public function terms(): Response
    {
        return $this->render('terms');
    }

    private function render(string $key): Response
    {
        return Inertia::render('Static/Show', [
            'staticPage' => $this->pages->payload($key),
        ]);
    }
}
