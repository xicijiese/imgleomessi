<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoDeduplicationLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['restored_at' => 'datetime'];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function retainedPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'retained_photo_id');
    }
}
