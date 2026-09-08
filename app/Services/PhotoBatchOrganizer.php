<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Photo;
use Illuminate\Support\Collection;

class PhotoBatchOrganizer
{
    /**
     * @param  Collection<int, Photo>  $photos
     * @return array{updated: int}
     */
    public function updateCommonFields(Collection $photos, array $attributes): array
    {
        $updated = 0;

        foreach ($photos as $photo) {
            $photo->update($attributes);
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @param  array<int>  $tagIds
     * @return array{updated: int}
     */
    public function appendTags(Collection $photos, array $tagIds): array
    {
        $updated = 0;
        $tagIds = collect($tagIds)->filter()->map(fn (mixed $id): int => (int) $id)->unique()->all();

        foreach ($photos as $photo) {
            $photo->tags()->syncWithoutDetaching($tagIds);
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @param  array<int>  $categoryIds
     * @return array{updated: int, failed: int}
     */
    public function syncStandaloneCategories(Collection $photos, array $categoryIds): array
    {
        $categoryIds = collect($categoryIds)->filter()->map(fn (mixed $id): int => (int) $id)->unique()->values();

        if (! $this->isCompleteCategorySet($categoryIds->all())) {
            return [
                'updated' => 0,
                'failed' => $photos->count(),
            ];
        }

        $updated = 0;

        foreach ($photos as $photo) {
            $photo->categories()->sync($categoryIds->all());
            $updated++;
        }

        return [
            'updated' => $updated,
            'failed' => 0,
        ];
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return array{published: int, failed: int, failures: array<int, string>}
     */
    public function publish(Collection $photos): array
    {
        $published = 0;
        $failures = [];

        foreach ($photos as $photo) {
            if ($photo->publish()) {
                $published++;

                continue;
            }

            $failures[] = $this->publishFailureReason($photo);
        }

        return [
            'published' => $published,
            'failed' => count($failures),
            'failures' => $failures,
        ];
    }

    public function publishFailureReason(Photo $photo): string
    {
        if ($photo->status === 'archived') {
            return "{$photo->title}：已归档图片不能在批次整理页发布。";
        }

        if (blank($photo->title)) {
            return "{$photo->title}：标题不能为空。";
        }

        if (in_array($photo->copyright_status, ['restricted', 'remove_requested'], true)) {
            return "{$photo->title}：版权状态不允许发布。";
        }

        if (! $photo->hasCompleteCategorySet()) {
            return "{$photo->title}：未补齐 7 个主分类子项。";
        }

        return "{$photo->title}：不满足发布条件。";
    }

    /**
     * @param  array<int>  $categoryIds
     */
    public function isCompleteCategorySet(array $categoryIds): bool
    {
        $categoryIds = collect($categoryIds)->filter()->map(fn (mixed $id): int => (int) $id)->unique();
        $rootCount = Category::query()->roots()->count();

        if ($rootCount === 0 || $categoryIds->count() !== $rootCount) {
            return false;
        }

        $selectedParentCount = Category::query()
            ->whereIn('id', $categoryIds)
            ->children()
            ->distinct()
            ->count('parent_id');

        return $selectedParentCount === $rootCount;
    }
}
