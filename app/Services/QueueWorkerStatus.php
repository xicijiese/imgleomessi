<?php

namespace App\Services;

use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;

class QueueWorkerStatus
{
    public function connection(): string
    {
        return config('queue.default') === 'sync' ? 'database' : config('queue.default');
    }

    public function heartbeat(Looping $event): void
    {
        foreach (explode(',', $event->queue) as $queue) {
            Cache::put($this->key($event->connectionName, trim($queue)), true, 180);
        }
    }

    public function available(): bool
    {
        $connection = $this->connection();

        return (bool) Cache::get($this->key($connection, config('queue.connections.'.$connection.'.queue', 'default')), false);
    }

    private function key(string $connection, string $queue): string
    {
        return 'queue-worker:'.hash('sha256', $connection.':'.$queue);
    }
}
