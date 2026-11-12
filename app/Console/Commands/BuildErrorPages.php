<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildErrorPages extends Command
{
    protected $signature = 'errors:build';

    protected $description = '生成不依赖PHP和前端构建的静态错误页';

    public function handle(): int
    {
        File::ensureDirectoryExists(public_path('errors'));
        foreach ([401, 403, 404, 405, 410, 419, 429, 500, 502, 503, 504] as $status) {
            File::put(public_path('errors/'.$status.'.html'), view('errors.archive', ['status' => $status])->render());
        }
        $this->info('静态错误页已生成。');

        return self::SUCCESS;
    }
}
