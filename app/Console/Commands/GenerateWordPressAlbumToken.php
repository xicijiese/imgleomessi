<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateWordPressAlbumToken extends Command
{
    protected $signature = 'wordpress:album-token';

    protected $description = '生成一组 WordPress 相册接口令牌及配置哈希，不自动修改配置';

    public function handle(): int
    {
        $token = bin2hex(random_bytes(32));
        $this->warn('以下明文只交给 WordPress 管理员，请勿提交代码或发送到公开聊天。');
        $this->line('WordPress 只读令牌：'.$token);
        $this->line('图库 .env 配置：WORDPRESS_ALBUM_TOKEN_HASH='.hash('sha256', $token));
        $this->info('配置后执行 php artisan config:cache；替换哈希可撤销旧令牌。');

        return self::SUCCESS;
    }
}
