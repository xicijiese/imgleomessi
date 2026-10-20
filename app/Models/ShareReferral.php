<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareReferral extends Model
{
    protected $guarded = ['id'];

    public function link()
    {
        return $this->belongsTo(ShareLink::class, 'share_link_id');
    }
}
