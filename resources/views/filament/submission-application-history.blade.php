<div class="admin-review-events">
    @foreach ($events as $event)
        <div class="admin-review-event">
            <time>{{ $event->created_at->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') }}</time>
            <div>
                <p class="admin-review-event-action">{{ \App\Models\SubmissionEvent::ACTIONS[$event->action] ?? $event->action }}</p>
                <p class="admin-review-note">{{ $event->note }}</p>
            </div>
        </div>
    @endforeach
</div>
