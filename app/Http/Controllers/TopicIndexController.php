<?php

namespace App\Http\Controllers;

use App\Services\PublicTopicIndex;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopicIndexController extends Controller
{
    public function __invoke(Request $request, PublicTopicIndex $topics): Response
    {
        return Inertia::render('Topics/Index', [
            'topicIndex' => $topics->payload($request),
        ]);
    }
}
