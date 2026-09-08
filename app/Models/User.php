<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\UserCenterNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const STATUSES = [
        'active' => '正常',
        'banned' => '已封禁',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'status',
        'banned_until',
        'ban_reason',
        'moderation_note',
        'supporter_until',
        'profile_public',
        'profile_bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function photoFavorites(): HasMany
    {
        return $this->hasMany(PhotoFavorite::class);
    }

    public function submittedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'user_id');
    }

    public function handledReports(): HasMany
    {
        return $this->hasMany(Report::class, 'handled_by');
    }

    public function reviewedComments(): HasMany
    {
        return $this->hasMany(Comment::class, 'reviewed_by');
    }

    public function sponsorshipOrders(): HasMany
    {
        return $this->hasMany(SponsorshipOrder::class);
    }

    public function supporterProfile(): HasOne
    {
        return $this->hasOne(SupporterProfile::class);
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function equippedBadge(): HasOne
    {
        return $this->hasOne(UserBadge::class)->where('status', 'earned')->where('equipped', true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        return in_array($this->role, ['admin', 'editor'], true)
            && $this->status === 'active'
            && ! $this->isBanned();
    }

    public function isBanned(): bool
    {
        if ($this->status !== 'banned') {
            return false;
        }

        return $this->banned_until === null || $this->banned_until->isFuture();
    }

    public function isSupporter(): bool
    {
        return $this->supporter_until?->isFuture() === true || $this->supporterProfile()->exists();
    }

    public function ban(?string $reason = null, ?string $note = null, mixed $until = null): bool
    {
        $updated = $this->update([
            'status' => 'banned',
            'banned_until' => $until,
            'ban_reason' => $reason,
            'moderation_note' => $note,
        ]);

        if ($updated) {
            $this->notify(new UserCenterNotification(
                'account_status',
                '账号状态已更新',
                '你的账号已被限制互动权限，可继续浏览个人中心和历史记录。',
                '/me',
                'user',
                $this->id,
            ));
        }

        return $updated;
    }

    public function unban(?string $note = null): bool
    {
        $updated = $this->update([
            'status' => 'active',
            'banned_until' => null,
            'ban_reason' => null,
            'moderation_note' => $note,
        ]);

        if ($updated) {
            $this->notify(new UserCenterNotification(
                'account_status',
                '账号状态已恢复',
                '你的账号互动权限已恢复。',
                '/me',
                'user',
                $this->id,
            ));
        }

        return $updated;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'banned_until' => 'datetime',
            'supporter_until' => 'datetime',
            'profile_public' => 'boolean',
        ];
    }
}
