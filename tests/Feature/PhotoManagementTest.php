<?php

namespace Tests\Feature;

use App\Filament\Resources\Albums\Pages\ManageAlbums;
use App\Filament\Resources\Photos\Pages\ManagePhotos;
use App\Filament\Resources\Photos\PhotoResource;
use App\Filament\Resources\PhotoUploadBatches\Pages\ManagePhotoUploadBatches;
use App\Jobs\ProcessPhotoAnalysisJob;
use App\Models\Album;
use App\Models\Category;
use App\Models\Photo;
use App\Models\PhotoUploadBatch;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\GalleryTaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_delete_removes_only_selected_unprotected_photos_and_reports_skips(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $deletable = Photo::create(['title' => '可批量删除']);
        $protected = Photo::create(['title' => '受保护查重记录']);
        $untouched = Photo::create(['title' => '未选中的图片']);
        $album = Album::create(['title' => '保留相册', 'slug' => 'bulk-delete-album', 'cover_photo_id' => $deletable->id]);
        $deletable->albums()->sync([$album->id]);
        \App\Models\PhotoDeduplicationLog::create([
            'photo_id' => $protected->id, 'retained_photo_id' => $untouched->id,
            'sha256_hash' => str_repeat('a', 64), 'previous_status' => 'draft',
        ]);
        $page = Livewire::test(ManagePhotos::class)->selectTableRecords([$protected->id, $deletable->id]);
        $page->mountAction(TestAction::make('deleteSelected')->table()->bulk())
            ->assertMountedActionModalSee('已选中 2 张图片')
            ->assertMountedActionModalSee('无法恢复');
        $this->assertDatabaseHas('photos', ['id' => $deletable->id]);
        $page->callMountedAction()->assertNotified('已删除 1 张，跳过 1 张，失败 0 张');
        $this->assertDatabaseMissing('photos', ['id' => $deletable->id]);
        $this->assertDatabaseHas('photos', ['id' => $protected->id]);
        $this->assertDatabaseHas('photos', ['id' => $untouched->id]);
        $this->assertDatabaseHas('albums', ['id' => $album->id]);
        $this->assertNull($album->fresh()->cover_photo_id);
        $this->assertDatabaseMissing('album_photo', ['photo_id' => $deletable->id]);
        Livewire::test(ManagePhotos::class)->callAction(TestAction::make('delete')->table($protected))
            ->assertNotified('图片未删除');
        $this->assertDatabaseHas('photos', ['id' => $protected->id]);
    }

    public function test_all_photo_bulk_actions_persist_only_to_selected_records(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $photos = collect(range(1, 3))->map(fn ($i) => Photo::create([
            'title' => '批量测试'.$i, 'status' => 'draft', 'copyright_status' => 'unknown',
            'display_key' => 'display/'.$i.'.webp', 'thumbnail_key' => 'thumb/'.$i.'.webp',
        ]));
        $selected = $photos->take(2);
        $old = Tag::create(['name' => '原标签', 'slug' => 'old-bulk']);
        $new = Tag::create(['name' => '追加标签', 'slug' => 'new-bulk']);
        foreach ($photos as $photo) {
            $photo->tags()->sync([$old->id]);
        }
        $run = fn ($name, $data = []) => Livewire::test(ManagePhotos::class)
            ->selectTableRecords($selected->pluck('id')->all())
            ->callAction(TestAction::make($name)->table()->bulk(), $data)->assertHasNoActionErrors();

        $run('appendTags', ['tag_ids' => [$new->id]]);
        $run('appendTags', ['tag_ids' => [$new->id]]);
        foreach ($selected as $photo) {
            $this->assertEqualsCanonicalizing([$old->id, $new->id], $photo->tags()->pluck('tags.id')->all());
        }
        $this->assertSame([$old->id], $photos->last()->tags()->pluck('tags.id')->all());

        $run('bulkEventDate', ['event_date' => '2009年']);
        $run('bulkSourceUrl', ['source_url' => 'https://example.com/source']);
        $run('bulkCopyrightStatus', ['copyright_status' => 'credited']);
        $categories = [];
        foreach (Category::roots()->where('required_for_publish', true)->get() as $root) {
            $categories['category_root_'.$root->id] = [$root->children()->firstOrFail()->id];
        }
        $run('bulkCategories', $categories);
        foreach ($selected as $photo) {
            $photo = $photo->fresh();
            $this->assertSame(2009, $photo->event_year);
            $this->assertSame('https://example.com/source', $photo->source_url);
            $this->assertSame('credited', $photo->copyright_status);
            $this->assertTrue($photo->hasCompleteCategorySet());
        }
        $run('publishSelected');
        foreach ($selected as $photo) {
            $this->assertSame('published', $photo->fresh()->status);
        }
        $run('setDraft');
        foreach ($selected as $photo) {
            $this->assertSame('draft', $photo->fresh()->status);
            $this->assertNull($photo->fresh()->published_at);
            $this->assertFalse($photo->fresh()->publish_after_processing);
        }
        $run('archiveSelected');
        foreach ($selected as $photo) {
            $this->assertSame('archived', $photo->fresh()->status);
        }
        $untouched = $photos->last()->fresh();
        $this->assertSame('draft', $untouched->status);
        $this->assertNull($untouched->event_year);
        $this->assertNull($untouched->source_url);
        $this->assertSame('unknown', $untouched->copyright_status);
        $this->assertSame(0, $untouched->categories()->count());
    }

    public function test_photo_bulk_actions_reject_invalid_input_without_changing_records(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $photo = Photo::create(['title' => '异常输入测试']);
        foreach ([
            ['appendTags', ['tag_ids' => []], 'tag_ids'],
            ['appendTags', ['tag_ids' => [999999]], 'tag_ids'],
            ['bulkSourceUrl', ['source_url' => '不是网址'], 'source_url'],
        ] as [$name, $data, $field]) {
            Livewire::test(ManagePhotos::class)->selectTableRecords([$photo->id])
                ->callAction(TestAction::make($name)->table()->bulk(), $data)->assertHasActionErrors([$field]);
        }
        Livewire::test(ManagePhotos::class)->selectTableRecords([$photo->id])
            ->callAction(TestAction::make('bulkCategories')->table()->bulk(), [])->assertNotified('分类设置失败');
        Livewire::test(ManagePhotos::class)->selectTableRecords([$photo->id])
            ->callAction(TestAction::make('publishSelected')->table()->bulk())
            ->assertNotified('已发布 0 张，失败 1 张');
        $this->assertSame('draft', $photo->fresh()->status);
        $this->assertNull($photo->fresh()->source_url);
        $this->assertSame(0, $photo->tags()->count());
        $this->assertSame(0, $photo->categories()->count());
    }

    public function test_upload_and_bulk_edit_accept_year_or_exact_event_date_and_reject_invalid_date(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        Livewire::test(ManagePhotos::class)->callAction('bulkUpload', ['files' => [UploadedFile::fake()->image('old.jpg')], 'publish_mode' => 'draft', 'event_date' => '2008年'])->assertHasNoActionErrors();
        $photo = Photo::sole();
        $this->assertSame(2008, $photo->event_year);
        $this->assertNull($photo->event_date);
        Livewire::test(ManagePhotos::class)->selectTableRecords([$photo->id])
            ->callAction(TestAction::make('bulkEventDate')->table()->bulk(), ['event_date' => '2010年7月3日'])->assertHasNoActionErrors();
        $this->assertSame('2010-07-03', $photo->fresh()->event_date->toDateString());
        $this->assertNull($photo->fresh()->event_year);
        Livewire::test(ManagePhotos::class)->selectTableRecords([$photo->id])
            ->callAction(TestAction::make('bulkEventDate')->table()->bulk(), ['event_date' => '2010年2月30日'])->assertHasActionErrors(['event_date']);
        $this->assertSame('2010-07-03', $photo->fresh()->event_date->toDateString());
        Livewire::test(ManagePhotos::class)->callAction(TestAction::make('edit')->table($photo), ['event_date' => '2009年'])->assertHasNoActionErrors();
        $this->assertSame(2009, $photo->fresh()->event_year);
    }

    public function test_batch_names_can_be_applied_and_overridden_per_picture(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $component = Livewire::test(ManagePhotos::class)->mountAction('bulkUpload')
            ->fillForm(['files' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')], 'publish_mode' => 'draft', 'batch_title' => '庆祝']);
        $component->callAction(TestAction::make('applyBatchNames')->schemaComponent())->assertHasNoActionErrors();
        $files = array_values($component->get('mountedActions.0.data.files'));
        $key = hash('sha256', $files[0]->getPathname());
        $component->assertSet('mountedActions.0.data.file_titles.'.$key, '庆祝 01');
        $component->set('mountedActions.0.data.file_titles.'.$key, '单独命名')->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame(['单独命名', '庆祝 02'], Photo::orderBy('id')->pluck('title')->all());
    }

    public function test_upload_titles_follow_files_after_reordering_and_removing_files(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $component = Livewire::test(ManagePhotos::class)->mountAction('bulkUpload')
            ->fillForm(['files' => [UploadedFile::fake()->image('same.jpg'), UploadedFile::fake()->image('same.jpg'), UploadedFile::fake()->image('removed.jpg')], 'publish_mode' => 'draft']);
        $files = array_values($component->get('mountedActions.0.data.files'));
        $first = hash('sha256', $files[0]->getPathname());
        $second = hash('sha256', $files[1]->getPathname());
        $component->set('mountedActions.0.data.file_titles.'.$first, '第一张自定义')
            ->set('mountedActions.0.data.file_titles.'.$second, '第二张自定义')
            ->set('mountedActions.0.data.files', ['second' => $files[1], 'first' => $files[0]])
            ->callMountedAction()->assertHasNoActionErrors();
        $photos = Photo::orderBy('id')->get();
        $this->assertSame(['第二张自定义', '第一张自定义'], $photos->pluck('title')->all());
        foreach ($photos as $photo) {
            $this->assertSame('same.jpg', $photo->original_filename);
            $this->assertNotSame($photo->title, $photo->stored_filename);
            Storage::disk('public')->assertExists($photo->original_key);
        }
    }

    public function test_backend_lists_default_to_twenty_and_discard_legacy_ten_preference(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        foreach ([ManagePhotos::class, ManageAlbums::class, ManagePhotoUploadBatches::class] as $page) {
            session()->put('tables.'.md5($page).'_per_page', 10);
            $component = Livewire::test($page);
            $component->assertSet('tableRecordsPerPage', 20);
            $this->assertSame([20, 50, 100], $component->instance()->getTable()->getPaginationPageOptions());
        }
    }

    public function test_upload_can_add_custom_categories_and_reject_blank_names(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'editor', 'status' => 'active']));
        foreach (['scene', 'image-type', 'person-relation'] as $slug) {
            $root = Category::where('slug', $slug)->sole();
            $field = 'category_root_'.$root->id;
            $component = Livewire::test(ManagePhotos::class)->mountAction('bulkUpload');
            $action = TestAction::make('createOption')->schemaComponent($field, 'mountedActionSchema0');
            $component->callAction($action, ['name' => '新增上传选项'])->assertHasNoActionErrors();
            $option = $root->children()->where('name', '新增上传选项')->sole();
            $component->assertSet('mountedActions.0.data.'.$field, [$option->id]);
        }
        $component = Livewire::test(ManagePhotos::class)->mountAction('bulkUpload');
        $component->callAction($action, ['name' => '   '])->assertHasActionErrors(['name']);
        $this->assertSame(0, Category::where('name', '')->count());
    }

    public function test_upload_saves_multiple_values_for_all_eight_category_roots(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $data = ['files' => [UploadedFile::fake()->image('multiple.jpg')], 'publish_mode' => 'publish_after_processing', 'copyright_status' => 'credited'];
        $expected = [];
        foreach (Category::roots()->get() as $root) {
            $ids = $root->children()->pluck('id')->take(1)->all();
            if (in_array($root->slug, Category::MULTI_SELECT_ROOT_SLUGS, true)) {
                $ids[] = Category::create(['parent_id' => $root->id, 'name' => '额外分类', 'slug' => 'extra-'.$root->slug])->id;
                $data['category_root_'.$root->id] = $ids;
            } else {
                $data['category_root_'.$root->id] = $ids[0] ?? null;
            }
            $expected = array_merge($expected, $ids);
        }
        Livewire::test(ManagePhotos::class)->callAction('bulkUpload', $data)->assertHasNoActionErrors();
        $photo = Photo::sole();
        $this->assertEqualsCanonicalizing($expected, $photo->categories()->pluck('categories.id')->all());
        $this->assertTrue($photo->hasCompleteCategorySet());
        Livewire::test(ManagePhotos::class)->callAction(TestAction::make('edit')->table($photo), ['title' => '只改标题']);
        $this->assertEqualsCanonicalizing($expected, $photo->categories()->pluck('categories.id')->all());
    }

    public function test_admin_can_update_album_and_tag_selections_and_inherit_album_categories(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $root = Category::create(['name' => '生涯阶段', 'slug' => 'career-stage']);
        $oldCategory = Category::create(['name' => '旧阶段', 'slug' => 'old-stage', 'parent_id' => $root->id]);
        $newCategory = Category::create(['name' => '新阶段', 'slug' => 'new-stage', 'parent_id' => $root->id]);
        $oldAlbum = Album::create(['title' => '旧相册', 'slug' => 'old-album']);
        $newAlbum = Album::create(['title' => '新相册', 'slug' => 'new-album']);
        $newAlbum->categories()->sync([$newCategory->id]);
        $yearRoot = Category::create(['name' => '年份', 'slug' => 'year']);
        $year = Category::create(['name' => '2024年', 'slug' => 'year-2024', 'parent_id' => $yearRoot->id]);
        $secondAlbum = Album::create(['title' => '另一个相册', 'slug' => 'second-album']);
        $secondAlbum->categories()->sync([$year->id]);
        $oldTag = Tag::create(['name' => '旧标签']);
        $newTag = Tag::create(['name' => '新标签']);
        $photo = Photo::create(['title' => '关联编辑测试']);
        $photo->albums()->sync([$oldAlbum->id]);
        $photo->tags()->sync([$oldTag->id]);
        $photo->categories()->sync([$oldCategory->id]);

        Livewire::test(ManagePhotos::class)
            ->callAction(TestAction::make('edit')->table($photo), [
                'albums' => [$newAlbum->id, $secondAlbum->id],
                'tags' => [$newTag->id],
            ])
            ->assertHasNoActionErrors();

        $this->assertEqualsCanonicalizing([$newAlbum->id, $secondAlbum->id], $photo->albums()->pluck('albums.id')->all());
        $this->assertEquals([$newTag->id], $photo->tags()->pluck('tags.id')->all());
        $this->assertEqualsCanonicalizing([$newCategory->id, $year->id], $photo->categories()->pluck('categories.id')->all());
    }

    public function test_editing_only_a_title_preserves_existing_album_and_tag_selections(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $album = Album::create(['title' => '保留相册', 'slug' => 'retained-album']);
        $tag = Tag::create(['name' => '保留标签']);
        $photo = Photo::create(['title' => '原标题']);
        $photo->albums()->sync([$album->id]);
        $photo->tags()->sync([$tag->id]);

        Livewire::test(ManagePhotos::class)
            ->callAction(TestAction::make('edit')->table($photo), ['title' => '新标题'])
            ->assertHasNoActionErrors();

        $this->assertEquals([$album->id], $photo->albums()->pluck('albums.id')->all());
        $this->assertEquals([$tag->id], $photo->tags()->pluck('tags.id')->all());
        $this->assertSame('新标题', $photo->fresh()->title);
    }

    public function test_admin_can_explicitly_clear_album_and_tag_selections(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $album = Album::create(['title' => '移除相册', 'slug' => 'removed-album']);
        $tag = Tag::create(['name' => '移除标签']);
        $root = Category::create(['name' => '生涯阶段', 'slug' => 'career-stage']);
        $category = Category::create(['name' => '手动分类', 'slug' => 'manual-stage', 'parent_id' => $root->id]);
        $photo = Photo::create(['title' => '主动清空测试']);
        $photo->albums()->sync([$album->id]);
        $photo->tags()->sync([$tag->id]);

        Livewire::test(ManagePhotos::class)
            ->callAction(TestAction::make('edit')->table($photo), [
                'albums' => [],
                'tags' => [],
                'category_root_'.$root->id => $category->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertCount(0, $photo->albums()->get());
        $this->assertCount(0, $photo->tags()->get());
        $this->assertEquals([$category->id], $photo->categories()->pluck('categories.id')->all());
    }

    public function test_photo_management_only_offers_the_real_upload_entry(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        Livewire::test(ManagePhotos::class)
            ->assertSee('上传图片')
            ->assertActionExists('bulkUpload')
            ->assertActionDoesNotExist('create');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_admin_can_edit_photo_metadata_without_changing_file_keys_or_publication_status(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $photo = Photo::create([
            'title' => '原图片标题',
            'original_key' => 'photos/original.jpg',
            'display_key' => 'photos/display.webp',
            'thumbnail_key' => 'photos/thumbnail.webp',
            'original_filename' => 'original.jpg',
            'stored_filename' => 'stored.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'width' => 640,
            'height' => 480,
        ]);

        Livewire::test(ManagePhotos::class)
            ->callAction(TestAction::make('edit')->table($photo), [
                'title' => '修改后的图片标题',
                'description' => '保留完整资料和关联关系',
                'source_url' => 'https://example.com/photo',
                'original_key' => 'tampered/original.jpg',
                'display_key' => 'tampered/display.webp',
                'thumbnail_key' => 'tampered/thumbnail.webp',
                'original_filename' => 'tampered.jpg',
                'stored_filename' => 'tampered-stored.jpg',
                'mime_type' => 'image/png',
                'file_size' => 9999,
                'width' => 1200,
                'height' => 800,
            ])
            ->assertHasNoActionErrors();

        $photo->refresh();
        $this->assertSame('修改后的图片标题', $photo->title);
        $this->assertSame('https://example.com/photo', $photo->source_url);
        $this->assertSame('photos/original.jpg', $photo->original_key);
        $this->assertSame('photos/display.webp', $photo->display_key);
        $this->assertSame('photos/thumbnail.webp', $photo->thumbnail_key);
        $this->assertSame('draft', $photo->status);
        $this->assertSame('original.jpg', $photo->original_filename);
        $this->assertSame('stored.jpg', $photo->stored_filename);
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertSame(1024, $photo->file_size);
        $this->assertSame(640, $photo->width);
        $this->assertSame(480, $photo->height);
    }

    public function test_admin_can_upload_a_draft_with_album_and_tags(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $album = Album::create(['title' => '上传测试相册', 'slug' => 'upload-layout-album']);
        $tag = Tag::create(['name' => '上传测试标签']);

        Livewire::test(ManagePhotos::class)
            ->callAction('bulkUpload', [
                'files' => [UploadedFile::fake()->image('layout-upload.jpg', 120, 80)],
                'source_url' => 'https://example.com/source',
                'publish_mode' => 'draft',
                'copyright_status' => 'unknown',
                'album_id' => $album->id,
                'tag_ids' => [$tag->id],
                'auto_ocr' => false,
            ])
            ->assertHasNoActionErrors();

        $photo = Photo::query()->sole();
        $this->assertSame('draft', $photo->status);
        $this->assertFalse($photo->publish_after_processing);
        $this->assertSame('https://example.com/source', $photo->source_url);
        $this->assertEquals([$album->id], $photo->albums()->pluck('albums.id')->all());
        $this->assertEquals([$tag->id], $photo->tags()->pluck('tags.id')->all());
        Storage::disk('public')->assertExists($photo->original_key);
    }

    public function test_upload_entry_stores_multiple_real_files_and_queues_their_processing(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        Livewire::test(ManagePhotos::class)
            ->callAction('bulkUpload', [
                'files' => [
                    UploadedFile::fake()->image('first.jpg', 120, 80),
                    UploadedFile::fake()->image('second.png', 60, 90),
                ],
                'publish_mode' => 'draft',
                'copyright_status' => 'unknown',
                'auto_ocr' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('photos', 2);
        $batch = PhotoUploadBatch::query()->sole();
        $this->assertSame(2, $batch->total_count);
        $this->assertSame(2, $batch->success_count);
        foreach (Photo::all() as $photo) {
            Storage::disk('public')->assertExists($photo->original_key);
            $this->assertSame('draft', $photo->status);
            $this->assertSame($batch->id, $photo->photo_upload_batch_id);
            $this->assertEqualsCanonicalizing(['metadata', 'hash', 'datawanxiang_derivatives'], $photo->processingJobs()->pluck('type')->all());
        }
        Queue::assertPushed(ProcessPhotoAnalysisJob::class, 6);
    }

    public function test_upload_entry_requires_a_file_and_does_not_create_an_empty_archive(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        Livewire::test(ManagePhotos::class)
            ->callAction('bulkUpload', [
                'files' => [],
                'publish_mode' => 'draft',
                'copyright_status' => 'unknown',
            ])
            ->assertHasActionErrors(['files' => 'required']);

        $this->assertDatabaseCount('photos', 0);
        $this->assertDatabaseCount('photo_upload_batches', 0);
        $this->assertDatabaseCount('processing_jobs', 0);
    }

    public function test_photo_tables_are_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('photos'));
        $this->assertTrue(Schema::hasTable('photo_category'));
        $this->assertTrue(Schema::hasTable('photo_tag'));
        $this->assertTrue(Schema::hasTable('photo_upload_batches'));
        $this->assertTrue(Schema::hasColumns('photos', [
            'uuid',
            'title',
            'description',
            'original_filename',
            'stored_filename',
            'taken_at',
            'event_date',
            'source_url',
            'copyright_status',
            'status',
            'publish_after_processing',
            'width',
            'height',
            'mime_type',
            'file_size',
            'original_key',
            'display_key',
            'thumbnail_key',
            'uploaded_by',
            'photo_upload_batch_id',
            'published_at',
        ]));
    }

    public function test_photo_defaults_to_draft_and_unknown_copyright_status(): void
    {
        $photo = Photo::query()->create([
            'title' => '2022 世界杯决赛捧杯',
            'display_key' => 'photos/derived/test/display.webp',
            'thumbnail_key' => 'photos/derived/test/thumbnail.webp',
        ]);

        $this->assertNotEmpty($photo->uuid);
        $this->assertSame('draft', $photo->status);
        $this->assertSame('unknown', $photo->copyright_status);
    }

    public function test_photo_can_link_source_categories_tags_albums_and_upload_batch(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $sourceUrl = 'https://example.com/messi-photo';
        $tag = Tag::query()->create([
            'name' => '捧杯',
        ]);
        $album = Album::query()->create([
            'title' => '2022 世界杯决赛',
            'slug' => '2022-world-cup-final',
        ]);
        $batch = PhotoUploadBatch::query()->create([
            'mode' => 'standalone',
            'status' => 'completed',
            'total_count' => 1,
            'success_count' => 1,
        ]);
        $photo = Photo::query()->create([
            'title' => '2022 世界杯决赛捧杯',
            'source_url' => $sourceUrl,
            'photo_upload_batch_id' => $batch->id,
        ]);

        $categoryIds = Category::query()->children()->where('name', '待补充')->pluck('id');
        $photo->categories()->sync($categoryIds);
        $photo->tags()->sync([$tag->id]);
        $photo->albums()->sync([$album->id]);

        $this->assertSame($sourceUrl, $photo->source_url);
        $this->assertTrue($photo->uploadBatch->is($batch));
        $this->assertTrue($photo->categories()->whereKey($categoryIds->first())->exists());
        $this->assertTrue($photo->tags()->whereKey($tag->id)->exists());
        $this->assertTrue($photo->albums()->whereKey($album->id)->exists());
    }

    public function test_photo_requires_the_three_required_root_categories_before_publish(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);

        $photo = Photo::query()->create([
            'title' => '2022 世界杯决赛捧杯',
            'display_key' => 'photos/derived/test/display.webp',
            'thumbnail_key' => 'photos/derived/test/thumbnail.webp',
        ]);
        $requiredCategoryIds = Category::query()
            ->children()
            ->whereIn('parent_id', Category::requiredRootIds())
            ->where('name', '待补充')
            ->pluck('id');

        $photo->categories()->sync($requiredCategoryIds->take(2));
        $this->assertFalse($photo->hasCompleteCategorySet());
        $this->assertFalse($photo->publish());
        $this->assertSame('draft', $photo->refresh()->status);

        $photo->categories()->sync($requiredCategoryIds);
        $this->assertTrue($photo->hasCompleteCategorySet());
        $this->assertTrue($photo->publish());
        $this->assertSame('published', $photo->refresh()->status);
        $this->assertNotNull($photo->published_at);
    }

    public function test_archived_photo_can_be_restored_as_draft(): void
    {
        $photo = Photo::query()->create([
            'title' => '待恢复图片',
            'status' => 'archived',
            'publish_after_processing' => true,
            'published_at' => now(),
        ]);

        $this->assertTrue($photo->restoreFromArchive());
        $this->assertSame('draft', $photo->refresh()->status);
        $this->assertFalse($photo->publish_after_processing);
        $this->assertNull($photo->published_at);
        $this->assertFalse($photo->restoreFromArchive());
    }

    public function test_published_scope_only_returns_published_photos(): void
    {
        Photo::query()->create([
            'title' => '草稿图片',
            'status' => 'draft',
        ]);
        Photo::query()->create([
            'title' => '已发布图片',
            'status' => 'published',
        ]);

        $this->assertSame(1, Photo::query()->published()->count());
        $this->assertSame('已发布图片', Photo::query()->published()->value('title'));
    }

    public function test_admin_can_visit_photo_resource(): void
    {
        $this->seed(GalleryTaxonomySeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(PhotoResource::getUrl())->assertOk();
    }

    public function test_photo_resource_renders_similarity_action_for_existing_photo(): void
    {
        Photo::query()->create([
            'title' => '已有图片',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->get(PhotoResource::getUrl())->assertOk();
    }
}
