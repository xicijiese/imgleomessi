<div class="space-y-3">
@foreach ($events as $event)
    <p>{{ $event->created_at->timezone('Asia/Shanghai')->format('Y-m-d H:i:s') }} · {{ \App\Models\SubmissionEvent::ACTIONS[$event->action] ?? $event->action }} · {{ $event->note }}</p>
@endforeach
</div>
