<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PhotoSimilarityGroup extends Model
{
    protected $fillable = ['representative_photo_id'];

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'representative_photo_id');
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'photo_similarity_group_members');
    }
}
