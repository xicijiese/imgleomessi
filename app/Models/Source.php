<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $attributes = [
        'is_enabled' => true,
    ];

    protected $fillable = [
        'original_url',
        'published_at',
        'copyright_note',
        'internal_note',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
