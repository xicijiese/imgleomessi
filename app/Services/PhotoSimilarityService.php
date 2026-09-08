<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\PhotoAnalysisResult;
use App\Models\PhotoSimilarityCandidate;
use App\Models\User;
use RuntimeException;

class PhotoSimilarityService
{
    public const DISTANCE_THRESHOLD = 12;

    public function __construct(private readonly PhotoStorage $storage) {}

    public function generateCandidates(Photo $photo): int
    {
        $perceptualHash = $this->updatePerceptualHash($photo);

        $results = PhotoAnalysisResult::query()
            ->where('photo_id', '!=', $photo->id)
            ->whereNotNull('perceptual_hash')
            ->get(['photo_id', 'perceptual_hash']);

        $count = 0;

        foreach ($results as $result) {
            $distance = $this->hammingDistance($perceptualHash, (string) $result->perceptual_hash);

            if ($distance > self::DISTANCE_THRESHOLD) {
                continue;
            }

            $photoId = min($photo->id, (int) $result->photo_id);
            $candidatePhotoId = max($photo->id, (int) $result->photo_id);

            PhotoSimilarityCandidate::query()->updateOrCreate(
                [
                    'photo_id' => $photoId,
                    'candidate_photo_id' => $candidatePhotoId,
                ],
                [
                    'distance' => $distance,
                    'similarity_score' => round((64 - $distance) / 64 * 100, 2),
                ],
            );

            $count++;
        }

        return $count;
    }

    public function review(
        PhotoSimilarityCandidate $candidate,
        string $status,
        ?User $reviewer = null,
    ): PhotoSimilarityCandidate {
        if (! array_key_exists($status, PhotoSimilarityCandidate::STATUSES)) {
            throw new RuntimeException('未知相似候选处理状态：'.$status);
        }

        $candidate->update([
            'status' => $status,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ]);

        return $candidate->refresh();
    }

    private function updatePerceptualHash(Photo $photo): string
    {
        if (blank($photo->original_key)) {
            throw new RuntimeException('图片缺少原图存储 Key。');
        }

        $storage = $this->storage->diskForKey($photo->original_key);

        if (! $storage->exists($photo->original_key)) {
            throw new RuntimeException('原图文件不存在：'.$photo->original_key);
        }

        $contents = $storage->get($photo->original_key);
        $hash = $this->dHash($contents);

        PhotoAnalysisResult::query()->updateOrCreate(
            ['photo_id' => $photo->id],
            [
                'perceptual_hash' => $hash,
                'error_message' => null,
                'processed_at' => now(),
            ],
        );

        return $hash;
    }

    private function dHash(string $contents): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('当前 PHP 未启用 GD，无法生成相似图特征。');
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('无法读取图片内容，无法生成相似图特征。');
        }

        $resized = imagecreatetruecolor(9, 8);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, 9, 8, imagesx($source), imagesy($source));

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $left = $this->luminance(imagecolorat($resized, $x, $y));
                $right = $this->luminance(imagecolorat($resized, $x + 1, $y));
                $bits .= $left > $right ? '1' : '0';
            }
        }

        imagedestroy($resized);
        imagedestroy($source);

        $hash = '';

        for ($offset = 0; $offset < 64; $offset += 4) {
            $hash .= dechex(bindec(substr($bits, $offset, 4)));
        }

        return $hash;
    }

    private function luminance(int $rgb): int
    {
        $red = ($rgb >> 16) & 0xff;
        $green = ($rgb >> 8) & 0xff;
        $blue = $rgb & 0xff;

        return (int) round(($red * 299 + $green * 587 + $blue * 114) / 1000);
    }

    private function hammingDistance(string $left, string $right): int
    {
        $leftBinary = hex2bin($left);
        $rightBinary = hex2bin($right);

        if ($leftBinary === false || $rightBinary === false || strlen($leftBinary) !== strlen($rightBinary)) {
            throw new RuntimeException('相似图特征格式无效。');
        }

        $distance = 0;

        for ($index = 0; $index < strlen($leftBinary); $index++) {
            $value = ord($leftBinary[$index]) ^ ord($rightBinary[$index]);

            while ($value > 0) {
                $distance += $value & 1;
                $value >>= 1;
            }
        }

        return $distance;
    }
}