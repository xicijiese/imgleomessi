<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Photo;
use App\Models\SupporterProfile;
use App\Models\User;
use App\Models\UserBadge;

class PublicUserProfile
{
    public function __construct(private readonly PublicHomepage $homepage, private readonly PublicSeo $seo) {}

    /**
     * @return array<string, mixed>
     */
    public function show(User $user, ?User $viewer = null): array
    {
        abort_unless($this->canView($user), 404);

        $user->loadMissing('supporterProfile');

        $comments = $this->publicComments($user)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Comment $comment): array => $this->commentItem($comment))
            ->all();

        $badges = $user->userBadges()
            ->where('status', 'earned')
            ->whereHas('badge', fn ($query) => $query->active())
            ->with('badge')
            ->orderByDesc('equipped')
            ->orderByDesc('awarded_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserBadge $userBadge): array => $this->badgeItem($userBadge))
            ->all();

        $supporterProfile = $this->publicSupporterProfile($user);

        return [
            ...$this->homepage->shell(),
            'seo' => $this->seo->userProfile($user->name, '/users/'.$user->id),
            'profile' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'bio' => $user->profile_bio,
                    'joined_month' => $user->created_at?->format('Y-m'),
                    'public_url' => '/users/'.$user->id,
                    'is_owner' => $viewer?->is($user) ?? false,
                ],
                'summary' => [
                    'badges_count' => count($badges),
                    'public_comments_count' => $this->publicComments($user)->count(),
                    'is_public_supporter' => $supporterProfile instanceof SupporterProfile,
                ],
                'supporter' => $this->supporterItem($supporterProfile),
                'equipped_badge' => collect($badges)->first(fn (array $badge): bool => $badge['is_equipped']),
                'badges' => $badges,
                'comments' => $comments,
            ],
        ];
    }

    public function canView(User $user): bool
    {
        return $user->profile_public === true && ! $user->isBanned();
    }

    private function publicComments(User $user): mixed
    {
        return $user->comments()
            ->discussion()
            ->published()
            ->whereHas('photo', fn ($query) => $this->publicPhotoQuery($query))
            ->with('photo');
    }

    private function publicPhotoQuery(mixed $query): mixed
    {
        return $query->where('status', 'published')
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested']);
    }

    private function publicSupporterProfile(User $user): ?SupporterProfile
    {
        $profile = $user->supporterProfile;

        if (! $profile instanceof SupporterProfile || ! $profile->show_publicly || $profile->total_amount_cents <= 0) {
            return null;
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function supporterItem(?SupporterProfile $profile): ?array
    {
        if (! $profile instanceof SupporterProfile) {
            return null;
        }

        return [
            'display_name' => $profile->displayName(),
            'badge_level' => $profile->badge_level,
            'badge_label' => $profile->badgeLabel(),
            'last_supported_at' => $profile->last_supported_at?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function badgeItem(UserBadge $userBadge): array
    {
        $badge = $userBadge->badge;

        return [
            'id' => $badge->id,
            'name' => $badge->name,
            'slug' => $badge->slug,
            'description' => $badge->description,
            'icon_key' => $badge->icon_key,
            'color' => $badge->color,
            'rule_label' => $badge->rule_label,
            'is_equipped' => $userBadge->equipped,
            'awarded_at' => $userBadge->awarded_at?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentItem(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'content' => str($comment->content)->limit(160)->toString(),
            'created_at' => $comment->created_at?->format('Y-m-d'),
            'photo' => $this->photoItem($comment->photo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoItem(?Photo $photo): array
    {
        if (! $photo instanceof Photo) {
            return [
                'title' => '图片暂不可见',
                'url' => null,
                'image_url' => null,
            ];
        }

        return [
            'title' => $photo->title,
            'url' => '/photos/'.$photo->uuid,
            'image_url' => PublicMediaUrl::fromPublicDisk($photo->display_key ?: $photo->thumbnail_key),
        ];
    }
}
