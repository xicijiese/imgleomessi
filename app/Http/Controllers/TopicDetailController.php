<?php

namespace App\Http\Controllers;

use App\Services\PublicTopicDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopicDetailController extends Controller
{
    public function __invoke(Request $request, string $slug, PublicTopicDetail $topics): Response
    {
        return Inertia::render('Topics/Show', [
            'topicDetail' => $topics->payload($request, $slug),
        ]);
    }
}
