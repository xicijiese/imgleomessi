<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class UserAvatarService
{
    public function __construct(private readonly PhotoStorage $storage) {}

    public function replace(User $user, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = 'avatars/'.$user->getKey().'/'.Str::uuid().'.'.$extension;
        $disk = $this->storage->diskForKey();
        $stored = $disk->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'public']);

        if (! is_string($stored)) {
            throw new RuntimeException('头像文件保存失败。');
        }

        $oldPath = $user->avatar_url;
        $user->forceFill(['avatar_url' => $stored])->save();

        if (filled($oldPath) && $oldPath !== $stored) {
            $this->storage->diskForKey($oldPath)->delete($oldPath);
        }

        return $stored;
    }

    public function remove(User $user): void
    {
        $path = $user->avatar_url;

        if (filled($path)) {
            $this->storage->diskForKey($path)->delete($path);
        }

        $user->forceFill(['avatar_url' => null])->save();
    }

    public function url(?string $path): ?string
    {
        return $this->storage->url($path);
    }
}
