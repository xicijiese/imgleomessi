<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    public const TYPES = [
        '动作' => '动作',
        '情绪' => '情绪',
        '画质' => '画质',
        '人物关系' => '人物关系',
        '荣誉' => '荣誉',
        '画面内容' => '画面内容',
        '服装/装备' => '服装/装备',
        '地点' => '地点',
    ];

    protected $fillable = [
        'name',
        'type',
        'description',
        'sort_order',
    ];

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'photo_tag');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
