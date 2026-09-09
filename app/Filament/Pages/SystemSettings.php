<?php

namespace App\Filament\Pages;

use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\User;
use App\Services\HomepageSettings;
use App\Services\PhotoStorage;
use App\Services\StorageSettings;
use App\Services\StorageLaunchCheck;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = '系统运维';

    protected static ?string $navigationLabel = '系统设置';

    protected static ?string $title = '系统设置';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.system-settings';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(HomepageSettings $settings, StorageSettings $storageSettings): void
    {
        $this->form->fill(array_replace_recursive(
            $settings->formState(),
            ['storage' => $storageSettings->formState()],
        ));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('首页配置')
                    ->tabs([
                        Tab::make('站点基础')
                            ->schema([
                                Section::make('站点基础信息')
                                    ->schema([
                                        TextInput::make('site.name')
                                            ->label('站点名称')
                                            ->required()
                                            ->maxLength(255),
                                        FileUpload::make('site.logo_path')
                                            ->label('LOGO')
                                            ->image()
                                            ->disk('public')
                                            ->directory('settings/logo'),
                                        TextInput::make('site.search_placeholder')
                                            ->label('首页搜索占位文案')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('site.contact_email')
                                            ->label('联系邮箱')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('site.icp_text')
                                            ->label('备案信息')
                                            ->maxLength(255),
                                        TextInput::make('site.copyright_text')
                                            ->label('版权文字')
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),
                                Textarea::make('internal_note')
                                    ->label('内部备注')
                                    ->helperText('只在后台显示，不输出到前台。')
                                    ->rows(4),
                            ]),
                        Tab::make('导航')
                            ->schema([
                                Repeater::make('navigation.items')
                                    ->label('导航项')
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('名称')
                                            ->required()
                                            ->maxLength(80),
                                        TextInput::make('url')
                                            ->label('链接')
                                            ->required()
                                            ->maxLength(255),
                                        Toggle::make('enabled')
                                            ->label('启用')
                                            ->default(false),
                                    ])
                                    ->columns(3)
                                    ->reorderable()
                                    ->addActionLabel('添加导航项'),
                            ]),
                        Tab::make('头图轮播')
                            ->schema([
                                Repeater::make('hero_slides')
                                    ->label('头图')
                                    ->schema([
                                        Toggle::make('enabled')
                                            ->label('启用')
                                            ->default(true),
                                        FileUpload::make('desktop_image_path')
                                            ->label('桌面端图片')
                                            ->image()
                                            ->disk('public')
                                            ->directory('settings/hero'),
                                        FileUpload::make('mobile_image_path')
                                            ->label('移动端图片')
                                            ->image()
                                            ->disk('public')
                                            ->directory('settings/hero'),
                                        TextInput::make('title')
                                            ->label('标题')
                                            ->maxLength(255),
                                        TextInput::make('subtitle')
                                            ->label('副标题')
                                            ->maxLength(255),
                                        TextInput::make('button_label')
                                            ->label('按钮文字')
                                            ->maxLength(80),
                                        TextInput::make('button_url')
                                            ->label('按钮链接')
                                            ->maxLength(255),
                                    ])
                                    ->columns(2)
                                    ->reorderable()
                                    ->minItems(0)
                                    ->maxItems(5)
                                    ->addActionLabel('添加头图'),
                            ]),
                        Tab::make('分类模块')
                            ->schema([
                                Section::make('分类模块设置')
                                    ->schema([
                                        Toggle::make('category_module.enabled')
                                            ->label('启用')
                                            ->default(true),
                                        TextInput::make('category_module.title')
                                            ->label('模块标题')
                                            ->required()
                                            ->maxLength(120),
                                        TextInput::make('category_module.display_count')
                                            ->label('显示数量')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(12)
                                            ->required(),
                                        TextInput::make('category_module.more_url')
                                            ->label('更多跳转链接')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columns(4),
                                Repeater::make('category_module.tabs')
                                    ->label('分类导航与展示项')
                                    ->schema([
                                        Toggle::make('enabled')
                                            ->label('启用')
                                            ->default(true),
                                        Select::make('category_id')
                                            ->label('主分类')
                                            ->options(fn (): array => Category::query()->roots()->orderBy('sort_order')->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('label')
                                            ->label('自定义显示名')
                                            ->maxLength(80),
                                        Select::make('photo_ids')
                                            ->label('指定图片')
                                            ->options(fn (): array => self::publishedPhotoOptions())
                                            ->multiple()
                                            ->searchable()
                                            ->preload(),
                                        Select::make('album_ids')
                                            ->label('指定相册')
                                            ->options(fn (): array => self::publishedAlbumOptions())
                                            ->multiple()
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2)
                                    ->reorderable()
                                    ->addActionLabel('添加分类导航'),
                            ]),
                        Tab::make('最新照片')
                            ->schema([
                                Section::make('最新照片模块')
                                    ->schema([
                                        Toggle::make('latest_photos.enabled')
                                            ->label('启用')
                                            ->default(true),
                                        TextInput::make('latest_photos.title')
                                            ->label('模块标题')
                                            ->required()
                                            ->maxLength(120),
                                        TextInput::make('latest_photos.display_count')
                                            ->label('显示数量')
                                            ->numeric()
                                            ->minValue(3)
                                            ->maxValue(30)
                                            ->required(),
                                        Select::make('latest_photos.sort')
                                            ->label('自动补足排序')
                                            ->options([
                                                'published_at_desc' => '最新发布优先',
                                            ])
                                            ->required(),
                                        Select::make('latest_photos.pinned_photo_ids')
                                            ->label('指定置顶图片')
                                            ->options(fn (): array => self::publishedPhotoOptions())
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(4),
                            ]),
                        Tab::make('专题模块')
                            ->schema([
                                Section::make('专题模块设置')
                                    ->schema([
                                        Toggle::make('topic_module.enabled')
                                            ->label('启用')
                                            ->default(true),
                                        TextInput::make('topic_module.title')
                                            ->label('模块标题')
                                            ->required()
                                            ->maxLength(120),
                                        TextInput::make('topic_module.display_count')
                                            ->label('显示数量')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(12)
                                            ->required(),
                                    ])
                                    ->columns(3),
                                Repeater::make('topic_module.items')
                                    ->label('专题展示项')
                                    ->helperText('专题模型尚未落地前，先保留首页专题展示配置；后续专题切片会升级为专题选择器。')
                                    ->schema([
                                        Toggle::make('enabled')
                                            ->label('启用')
                                            ->default(true),
                                        TextInput::make('title')
                                            ->label('专题名称')
                                            ->required()
                                            ->maxLength(120),
                                        TextInput::make('url')
                                            ->label('专题详情链接')
                                            ->required()
                                            ->maxLength(255),
                                        FileUpload::make('cover_image_path')
                                            ->label('专题封面')
                                            ->image()
                                            ->disk('public')
                                            ->directory('settings/topics'),
                                        Textarea::make('description')
                                            ->label('专题说明')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        Select::make('album_ids')
                                            ->label('关联相册')
                                            ->options(fn (): array => self::publishedAlbumOptions())
                                            ->multiple()
                                            ->searchable()
                                            ->preload(),
                                        Select::make('photo_ids')
                                            ->label('精选图片')
                                            ->options(fn (): array => self::publishedPhotoOptions())
                                            ->multiple()
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2)
                                    ->reorderable()
                                    ->addActionLabel('添加专题'),
                            ]),
                        Tab::make('页脚')
                            ->schema([
                                Section::make('页脚基础')
                                    ->schema([
                                        TextInput::make('footer.copyright_text')
                                            ->label('版权文字')
                                            ->maxLength(255),
                                        TextInput::make('footer.icp_text')
                                            ->label('备案信息')
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),
                                Repeater::make('footer.links')
                                    ->label('页脚链接')
                                    ->schema([
                                        Toggle::make('enabled')
                                            ->label('启用')
                                            ->default(false),
                                        TextInput::make('label')
                                            ->label('名称')
                                            ->required()
                                            ->maxLength(80),
                                        TextInput::make('url')
                                            ->label('链接')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columns(3)
                                    ->reorderable()
                                    ->addActionLabel('添加页脚链接'),
                                Repeater::make('footer.social_links')
                                    ->label('中文社媒')
                                    ->schema([
                                        Toggle::make('enabled')
                                            ->label('启用')
                                            ->default(true),
                                        Select::make('platform')
                                            ->label('平台')
                                            ->options([
                                                'weibo' => '微博',
                                                'bilibili' => 'B 站',
                                                'xiaohongshu' => '小红书',
                                                'douyin' => '抖音',
                                                'wechat' => '微信公众号',
                                                'email' => '邮箱',
                                                'other' => '其他',
                                            ])
                                            ->required(),
                                        TextInput::make('label')
                                            ->label('显示名称')
                                            ->required()
                                            ->maxLength(80),
                                        TextInput::make('url')
                                            ->label('链接或联系方式')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columns(4)
                                    ->reorderable()
                                    ->addActionLabel('添加社媒'),
                            ]),
                        Tab::make('存储与处理')
                            ->schema([
                                Section::make('图片存储方式')
                                    ->description('本地指项目当前运行环境；腾讯云模式使用你自己的 COS 存储桶。切换前请先检测连接。')
                                    ->schema([
                                        Select::make('storage.mode')
                                            ->label('当前存储方式')
                                            ->options([
                                                'local' => '本地存储（当前运行环境）',
                                                'cos' => '腾讯云云存储（COS）',
                                            ])
                                            ->required(),
                                        Toggle::make('storage.cos.datawanxiang_enabled')
                                            ->label('启用数据万象处理')
                                            ->visible(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->helperText('只在后台队列中处理，不在前台请求中同步调用。'),
                                        Hidden::make('storage.cos.secret_key_saved')
                                            ->dehydrated(false),
                                        TextInput::make('storage.cos.secret_id')
                                            ->label('腾讯云 SecretId')
                                            ->visible(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->required(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->maxLength(255),
                                        TextInput::make('storage.cos.secret_key')
                                            ->label('腾讯云 SecretKey')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->required(fn (Get $get): bool => $get('storage.mode') === 'cos' && ! $get('storage.cos.secret_key_saved'))
                                            ->helperText(fn (Get $get): string => $get('storage.cos.secret_key_saved') ? '已保存；留空表示保留原凭证。' : '只在服务端加密保存，不会回显。')
                                            ->maxLength(255),
                                        TextInput::make('storage.cos.region')
                                            ->label('COS 地域')
                                            ->placeholder('例如：ap-guangzhou')
                                            ->visible(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->required(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->maxLength(80),
                                        TextInput::make('storage.cos.bucket')
                                            ->label('COS 存储桶')
                                            ->placeholder('例如：example-1250000000')
                                            ->visible(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->required(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->maxLength(255),
                                        TextInput::make('storage.cos.cdn_url')
                                            ->label('CDN 域名（可选）')
                                            ->url()
                                            ->visible(fn (Get $get): bool => $get('storage.mode') === 'cos')
                                            ->helperText('填写展示图/CDN 的公开域名；不填写时使用 COS 地址。')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),                    ])
                    ->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    public function save(HomepageSettings $settings, StorageSettings $storageSettings): void
    {
        $state = $this->form->getState();
        $settings->save($state, $this->currentUser());
        $storageSettings->save($state['storage'] ?? [], $this->currentUser());

        Notification::make()
            ->title('首页与存储设置已保存')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkStorage')
                ->label('检测当前存储')
                ->color('info')
                ->action(function (PhotoStorage $storage): void {
                    try {
                        $storage->healthCheck();

                        Notification::make()
                            ->title('当前存储读写检查通过')
                            ->success()
                            ->send();
                    } catch (Throwable $throwable) {
                        report($throwable);

                        Notification::make()
                            ->title('当前存储检查失败')
                            ->body($throwable->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('checkCloudConnectivity')
                ->label('检测 COS / 图片处理')
                ->color('info')
                ->form([
                    Select::make('photo_id')
                        ->label('验证 COS 中已有的原图')
                        ->options(fn (): array => Photo::query()
                            ->whereNotNull('original_key')
                            ->latest('updated_at')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn (Photo $photo): array => [
                                $photo->id => ($photo->title ?: '未命名图片').' #'.$photo->id,
                            ])
                            ->all())
                        ->required()
                        ->helperText('必须选择原图已经存在于当前 COS 存储桶中的图片；本地存储图片不能用于此检测。此操作只验证 COS 读写和原图可访问性。'),
                ])
                ->requiresConfirmation()
                ->modalHeading('检测 COS / 图片处理真实连通性')
                ->modalDescription('此操作会向你配置的 COS 写入并删除临时文件，并检查所选图片的原图对象。')
                ->action(function (array $data, PhotoStorage $storage): void {
                    try {
                        $photo = Photo::query()->find((int) ($data['photo_id'] ?? 0));

                        if (! $photo instanceof Photo || blank($photo->original_key)) {
                            throw new \RuntimeException('请选择一张包含原图 Key 的验证图片。');
                        }

                        if (! $storage->cosObjectExists($photo->original_key)) {
                            Notification::make()
                                ->title('验证图片不在当前 COS 存储桶中')
                                ->body('请先将这张图片上传到当前 COS 存储方式，再进行检测。')
                                ->danger()
                                ->send();

                            return;
                        }

                        $storage->healthCheck();

                        Notification::make()
                            ->title('COS / 图片处理连通性检查通过')
                            ->body('COS 读写正常，所选图片原图对象可访问。')
                            ->success()
                            ->send();
                    } catch (Throwable $throwable) {
                        report($throwable);

                        Notification::make()
                            ->title('COS / 数据万象连通性检查失败')
                            ->body('请检查存储方式、SecretId、SecretKey、地域、Bucket 和验证图片；详细异常已记录到后台日志。')
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('checkLaunch')
                ->label('上线前检查')
                ->color('warning')
                ->action(function (StorageLaunchCheck $check): void {
                    $report = $check->run();
                    $lines = $report['checks'];

                    foreach ($report['warnings'] as $warning) {
                        $lines[] = '提醒：'.$warning;
                    }

                    foreach ($report['failures'] as $failure) {
                        $lines[] = '问题：'.$failure;
                    }

                    $notification = Notification::make()
                        ->title($report['passed'] ? '上线前检查通过' : '上线前检查发现问题')
                        ->body(implode("\n", $lines));

                    if ($report['failures'] !== []) {
                        $notification->danger();
                    } elseif ($report['warnings'] !== []) {
                        $notification->warning();
                    } else {
                        $notification->success();
                    }

                    $notification->send();
                }),
            Action::make('restoreDefaults')
                ->label('恢复默认')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (HomepageSettings $settings): void {
                    $settings->save(HomepageSettings::defaults(), $this->currentUser());
                    $this->form->fill(array_replace_recursive($settings->formState(), ['storage' => app(StorageSettings::class)->formState()]));

                    Notification::make()
                        ->title('已恢复默认首页配置')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function publishedPhotoOptions(): array
    {
        return Photo::query()
            ->published()
            ->whereNotIn('copyright_status', ['restricted', 'remove_requested'])
            ->latest('published_at')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (Photo $photo): array => [
                $photo->id => $photo->title,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function publishedAlbumOptions(): array
    {
        return Album::query()
            ->published()
            ->whereHas('photos', fn ($query) => $query
                ->where('photos.status', 'published')
                ->whereNotIn('photos.copyright_status', ['restricted', 'remove_requested']))
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    private function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
