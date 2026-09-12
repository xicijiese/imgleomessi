<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'contribution_focus',
        'is_public',
        'sort_order',
        'role_before_grant',
        'role_granted_by_contributor',
        'granted_at',
        'granted_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'role_granted_by_contributor' => 'boolean',
            'sort_order' => 'integer',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}