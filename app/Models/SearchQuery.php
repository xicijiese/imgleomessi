<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchQuery extends Model
{
    protected $fillable = [
        'user_id',
        'keyword',
        'normalized_keyword',
        'source',
        'result_count',
        'filters_json',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'result_count' => 'integer',
            'filters_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
