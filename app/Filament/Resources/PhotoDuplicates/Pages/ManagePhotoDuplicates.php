<?php

namespace App\Filament\Resources\PhotoDuplicates\Pages;

use App\Filament\Resources\PhotoDuplicates\PhotoDuplicateResource;
use App\Jobs\ProcessPhotoGovernance;
use App\Models\Photo;
use App\Models\PhotoGovernanceRun;
use App\Services\QueueWorkerStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Bus;
use Livewire\Attributes\Locked;
use Throwable;

class ManagePhotoDuplicates extends ManageRecords
{
    protected static string $resource = PhotoDuplicateResource::class;

    #[Locked]
    public ?int $progressRunId = null;

    protected function scanType(): string
    {
        return 'duplicates';
    }

    public function getSubheading(): ?string
    {
        return '检查仅列出结果，由你选择保留与删除；不会自动清理图片。进度和结果可在任务弹窗查看。';
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()->poll('10s');
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('groups')->label('开始检查')
            ->requiresConfirmation()->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()
            ->modalDescription('后台检查现有图片并补充匹配关系，可能读取原图；使用COS时可能产生请求或流量费用。不自动删除或归档图片。')
            ->action(function (): void {
                abort_unless(auth()->user()?->isAdministrator(), 403);
                $existing = PhotoGovernanceRun::where('type', 'duplicates')
                    ->whereIn('status', ['pending', 'running'])->latest('id')->first();
                if ($existing) {
                    Notification::make()->warning()->title('已有任务进行中，已打开该任务进度，不会重复提交')->send();
                    $this->openProgress($existing->id);

                    return;
                }
                $maxId = Photo::max('id') ?? 0;
                $run = PhotoGovernanceRun::create([
                    'type' => $this->scanType(),
                    'operated_by' => auth()->id(),
                    'max_photo_id' => $maxId,
                    'total_count' => Photo::where('status', '!=', 'archived')->where('id', '<=', $maxId)->count(),
                ]);
                try {
                    Bus::dispatch(new ProcessPhotoGovernance($run->id));
                } catch (Throwable $exception) {
                    report($exception);
                    $run->update(['status' => 'failed', 'error_message' => '任务提交失败，请检查队列连接后重试。']);
                }
                $this->openProgress($run->id);
            }),
            Action::make('viewProgress')->label('查看最近进度')->color('gray')
                ->visible(fn (): bool => $this->latestRunId() !== null)
                ->action(function (): void {
                    abort_unless(auth()->user()?->isAdministrator(), 403);
                    $runId = $this->latestRunId();
                    abort_unless($runId, 404);
                    $this->openProgress($runId);
                }),
        ];
    }

    private function latestRunId(): ?int
    {
        return $this->progressRunId ?? PhotoGovernanceRun::where('type', $this->scanType())->latest('id')->value('id');
    }

    private function openProgress(int $runId): void
    {
        $this->progressRunId = $runId;
        $this->replaceMountedAction('progress', ['runId' => $runId]);
    }

    public function progressAction(): Action
    {
        return Action::make('progress')->authorize(fn (): bool => auth()->user()?->isAdministrator() ?? false)
            ->modalHeading('任务进度与结果')->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()
            ->modalContent(fn (array $arguments): View => $this->progressView($arguments))
            ->modalSubmitAction(false)->modalCancelActionLabel('关闭')->closeModalByClickingAway(false);
    }

    private function progressView(array $arguments): View
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $run = PhotoGovernanceRun::where('type', 'duplicates')
            ->findOrFail((int) ($arguments['runId'] ?? 0));

        return view('filament.photos.scan-progress', [
            'run' => $run,
            'workerAvailable' => app(QueueWorkerStatus::class)->available(),
            'existingPhotoIds' => Photo::whereIn('id', array_column($run->failures ?? [], 'photo_id'))->pluck('id')->all(),
        ]);
    }
}
