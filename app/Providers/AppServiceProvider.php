<?php

namespace App\Providers;

use App\Models\User;
use App\Models\UserLoginHistory;
use App\Services\QueueWorkerStatus;
use Illuminate\Auth\Events\Login;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Looping::class, fn (Looping $event) => app(QueueWorkerStatus::class)->heartbeat($event));

        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;

            if (! $user instanceof User) {
                return;
            }

            $now = now();
            $user->forceFill(['last_login_at' => $now])->saveQuietly();
            UserLoginHistory::query()->create([
                'user_id' => $user->getKey(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'logged_in_at' => $now,
            ]);
        });

        Gate::before(function (?User $user, string $ability, array $arguments): ?bool {
            if (! $user || $user->isAdministrator()) {
                return null;
            }

            $model = $arguments[0] ?? null;

            if ($user->role === 'editor' && (is_object($model) || is_string($model))) {
                return $user->canManageModel($model);
            }

            return null;
        });
    }
}
