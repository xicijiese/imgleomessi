<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
