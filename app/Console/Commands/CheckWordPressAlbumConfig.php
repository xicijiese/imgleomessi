<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;

class CheckWordPressAlbumConfig extends Command
{
    protected $signature = 'wordpress:album-check';

    protected $description = '检查相册接口配置是否加载，不显示令牌、哈希或其他配置';

    public function handle(): int
    {
        $active = (string) config('wordpress.token_hash');
        $valid = (bool) preg_match('/^[a-f0-9]{64}$/i', $active);
        $this->line('当前接口配置：'.($valid ? '已加载有效格式' : '未加载或格式不正确'));
        $this->line('配置缓存：'.(app()->configurationIsCached() ? '已启用' : '未启用'));
        try {
            $path = app()->environmentFilePath();
            $contents = is_readable($path) ? file_get_contents($path) : false;
            if ($contents === false) {
                $this->warn('无法读取环境文件，请检查文件权限。');

                return self::FAILURE;
            }
            $values = Dotenv::parse($contents);
            $stored = (string) ($values['WORDPRESS_ALBUM_TOKEN_HASH'] ?? '');
            $this->line('环境文件配置：'.(preg_match('/^[a-f0-9]{64}$/i', $stored) ? '有效格式' : '缺失或格式不正确'));
            $this->line('当前配置与环境文件：'.(hash_equals(strtolower($active), strtolower($stored)) ? '一致' : '不一致，请核对缓存及服务器环境变量'));
        } catch (\Throwable) {
            $this->error('环境文件无法解析；未输出文件内容，请检查配置格式。');

            return self::FAILURE;
        }
        $this->info('本检查只验证配置加载，不能证明 WordPress 实际发送的令牌正确或请求头已被转发。');

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
