<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareVisit extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_tick_at' => 'datetime', 'visible_seconds' => 'integer'];
    }

    public function link()
    {
        return $this->belongsTo(ShareLink::class, 'share_link_id');
    }
}
