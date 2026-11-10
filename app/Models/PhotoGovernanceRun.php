<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoGovernanceRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_count' => 'integer',
            'processed_count' => 'integer',
            'failed_count' => 'integer',
            'failures' => 'array',
            'result_count' => 'integer',
            'max_photo_id' => 'integer',
            'last_photo_id' => 'integer',
        ];
    }

    public function progressPercent(): int
    {
        if ($this->status === 'done') {
            return 100;
        }

        return $this->total_count > 0
            ? min(99, (int) floor($this->processed_count / $this->total_count * 100))
            : 0;
    }

    public function successfulCount(): int
    {
        return max(0, $this->processed_count - $this->failed_count);
    }
}
