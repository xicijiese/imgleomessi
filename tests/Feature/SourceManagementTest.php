<?php

namespace Tests\Feature;

use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sources_table_is_created_with_confirmed_phase_one_columns(): void
    {
        $this->assertTrue(Schema::hasTable('sources'));
        $this->assertTrue(Schema::hasColumns('sources', [
            'original_url',
            'published_at',
            'copyright_note',
            'internal_note',
            'is_enabled',
        ]));
    }

    public function test_source_defaults_to_enabled(): void
    {
        $source = Source::query()->create([
            'original_url' => 'https://example.com/messi-photo',
        ]);

        $this->assertTrue($source->is_enabled);
    }

    public function test_enabled_scope_only_returns_enabled_sources(): void
    {
        Source::query()->create([
            'original_url' => 'https://example.com/enabled',
            'is_enabled' => true,
        ]);
        Source::query()->create([
            'original_url' => 'https://example.com/disabled',
            'is_enabled' => false,
        ]);

        $this->assertSame(1, Source::query()->enabled()->count());
        $this->assertSame('https://example.com/enabled', Source::query()->enabled()->value('original_url'));
    }

    public function test_internal_note_is_a_backend_only_field_documented_on_the_model(): void
    {
        $source = Source::query()->create([
            'original_url' => 'https://example.com/messi-photo',
            'published_at' => '2022-12-18 18:00:00',
            'copyright_note' => '前台可展示的版权备注',
            'internal_note' => '只给后台看的处理线索',
        ]);

        $this->assertSame('前台可展示的版权备注', $source->copyright_note);
        $this->assertSame('只给后台看的处理线索', $source->internal_note);
    }

    public function test_admin_can_visit_source_resource(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(SourceResource::getUrl())->assertOk();
    }
}
