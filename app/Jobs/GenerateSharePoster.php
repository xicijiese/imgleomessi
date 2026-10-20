<?php

namespace App\Jobs;

use App\Models\ShareLink;
use App\Services\PhotoStorage;
use App\Services\SharePromotion;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateSharePoster implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $linkId, public string $fingerprint) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('share-poster:'.$this->linkId))->expireAfter(150)->releaseAfter(15)];
    }

    public static function fingerprint(array $target): string
    {
        return hash('sha256', json_encode($target, JSON_UNESCAPED_UNICODE).config('app.url').'poster-v1');
    }

    public function handle(SharePromotion $shares, PhotoStorage $storage): void
    {
        $link = ShareLink::findOrFail($this->linkId);
        $target = $shares->available($link);
        if ($link->poster_fingerprint !== $this->fingerprint || self::fingerprint($target) !== $this->fingerprint) {
            return;
        }
        $font = collect([config('sharing.poster_font'), 'C:/Windows/Fonts/msyh.ttc', '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc', '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc'])->first(fn ($path) => $path && is_file($path));
        if (! $font || ! function_exists('imagettftext')) {
            throw new RuntimeException('海报需要 PHP GD FreeType 和中文字体，请配置 SHARE_POSTER_FONT。');
        }
        $canvas = imagecreatetruecolor(900, 1240);
        $white = imagecolorallocate($canvas, 255, 255, 254);
        $blue = imagecolorallocate($canvas, 9, 64, 103);
        $pale = imagecolorallocate($canvas, 216, 238, 254);
        imagefill($canvas, 0, 0, $white);
        imagefilledrectangle($canvas, 32, 100, 868, 805, $pale);
        if ($target['image']) {
            $disk = $storage->diskForKey($target['image']);
            $bytes = $disk->get($target['image']);
            if (strlen($bytes) > 20 * 1024 * 1024) {
                throw new RuntimeException('海报封面过大，请更换封面');
            }
            $size = getimagesizefromstring($bytes);
            if (! $size || $size[0] * $size[1] > 24000000) {
                throw new RuntimeException('海报封面尺寸无效');
            }
            $image = imagecreatefromstring($bytes);
            if (! $image) {
                throw new RuntimeException('无法读取海报封面');
            }
            $ratio = min(836 / imagesx($image), 705 / imagesy($image));
            $w = (int) (imagesx($image) * $ratio);
            $h = (int) (imagesy($image) * $ratio);
            imagecopyresampled($canvas, $image, 32 + (int) ((836 - $w) / 2), 100 + (int) ((705 - $h) / 2), 0, 0, $w, $h, imagesx($image), imagesy($image));
            imagedestroy($image);
        } else {
            imagettftext($canvas, 32, 0, 110, 450, $blue, $font, '一起保存梅西的影像记忆');
        }
        imagettftext($canvas, 24, 0, 32, 62, $blue, $font, mb_strimwidth($target['site'], 0, 45, '…'));
        $title = mb_strimwidth($target['title'], 0, 90, '…');
        foreach (mb_str_split($title, 22) as $i => $line) {
            if ($i > 2) {
                break;
            } imagettftext($canvas, 26, 0, 32, 865 + $i * 42, $blue, $font, $line);
        }
        $summary = mb_strimwidth(strip_tags((string) $target['description']), 0, 55, '…');
        foreach (mb_str_split($summary, 25) as $i => $line) {
            imagettftext($canvas, 16, 0, 32, 990 + $i * 30, $blue, $font, $line);
        }
        imagettftext($canvas, 16, 0, 32, 1090, $blue, $font, '扫码查看影像档案');
        imagettftext($canvas, 14, 0, 32, 1160, $blue, $font, mb_strimwidth($target['source'], 0, 58, '…'));
        imagettftext($canvas, 14, 0, 32, 1200, $blue, $font, '分享展示资料，不代表拥有图片版权');
        $matrix = Encoder::encode($shares->url($link), ErrorCorrectionLevel::M())->getMatrix();
        $scale = max(1, (int) floor(230 / ($matrix->getWidth() + 8)));
        $offsetX = 620;
        $offsetY = 975;
        imagefilledrectangle($canvas, $offsetX, $offsetY, 880, 1230, $white);
        for ($x = 0; $x < $matrix->getWidth(); $x++) {
            for ($y = 0; $y < $matrix->getHeight(); $y++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle($canvas, $offsetX + ($x + 4) * $scale, $offsetY + ($y + 4) * $scale, $offsetX + ($x + 5) * $scale - 1, $offsetY + ($y + 5) * $scale - 1, $blue);
                }
            }
        }
        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);
        $shares->available($link->fresh());
        $path = 'sharing/posters/'.$link->code.'-'.$this->fingerprint.'.png';
        if (! Storage::disk('local')->put($path, $png)) {
            throw new RuntimeException('海报保存失败');
        }
        $old = $link->poster_path;
        ShareLink::whereKey($link->id)->where('poster_fingerprint', $this->fingerprint)->update(['poster_path' => $path, 'poster_status' => 'ready', 'poster_error' => null]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }

    public function failed(?Throwable $error): void
    {
        report($error);
        ShareLink::whereKey($this->linkId)->where('poster_fingerprint', $this->fingerprint)->update(['poster_status' => 'failed', 'poster_error' => '生成失败，请重试；管理员可检查队列日志、GD 和中文字体配置。']);
    }
}
