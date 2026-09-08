<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoAnalysisResult extends Model
{
    protected $fillable = [
        'photo_id',
        'width',
        'height',
        'mime_type',
        'file_size',
        'sha256_hash',
        'perceptual_hash',
        'exif_json',
        'ocr_text',
        'ci_labels_json',
        'ci_quality_json',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
            'perceptual_hash' => 'string',
            'exif_json' => 'array',
            'ci_labels_json' => 'array',
            'ci_quality_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
