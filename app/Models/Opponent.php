<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Opponent extends Model
{
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'slug',
        'country',
        'aliases',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'opponent_photo');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
