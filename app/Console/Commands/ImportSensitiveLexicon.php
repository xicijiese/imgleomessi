<?php

namespace App\Console\Commands;

use App\Services\LocalSensitiveLexicon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportSensitiveLexicon extends Command
{
    protected $signature = 'moderation:import-sensitive-words {--apply : 确认备份后执行首次导入}';

    protected $description = '将已启用的本地词库导入后台管理，默认仅检查';

    public function handle(LocalSensitiveLexicon $lexicon): int
    {
        $this->line('连接：'.DB::connection()->getDriverName().'；数据库：'.DB::connection()->getDatabaseName());
        $result = $lexicon->import((bool) $this->option('apply'));
        if ($result['completed']) {
            $this->info('已完成首次导入；保留后台后续修改、停用和删除，不重复恢复词条。');

            return self::SUCCESS;
        }
        $this->info('新增：'.$result['imported'].'；已有或归一化重复跳过：'.$result['skipped']);
        $this->line($this->option('apply') ? '导入完成；评论检测现在以后台规则为准。' : '仅检查。备份数据库后使用 --apply；已有规则与停用状态保持。');

        return self::SUCCESS;
    }
}
