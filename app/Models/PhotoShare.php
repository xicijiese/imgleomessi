<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoShare extends Model
{
    protected $fillable = [
        'photo_id',
        'user_id',
        'channel',
        'page_url',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'photo_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
