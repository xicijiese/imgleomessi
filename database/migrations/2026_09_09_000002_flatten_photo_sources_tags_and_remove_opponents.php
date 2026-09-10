<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('photos', 'source_url')) {
            Schema::table('photos', function (Blueprint $table): void {
                $table->string('source_url', 2048)->nullable()->after('event_date');
            });
        }

        if (Schema::hasTable('sources') && Schema::hasColumn('photos', 'source_id')) {
            DB::statement(<<<'SQL'
                UPDATE photos
                SET source_url = (
                    SELECT original_url
                    FROM sources
                    WHERE sources.id = photos.source_id
                )
                WHERE source_id IS NOT NULL
                  AND source_url IS NULL
                  AND EXISTS (
                      SELECT 1
                      FROM sources
                      WHERE sources.id = photos.source_id
                        AND sources.original_url IS NOT NULL
                  )
            SQL);
        }

        if (Schema::hasTable('opponents') && Schema::hasTable('opponent_photo')) {
            $now = now();

            DB::table('opponents')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->each(function (object $opponent) use ($now): void {
                    $name = trim((string) $opponent->name);

                    if ($name === '') {
                        return;
                    }

                    $tagId = DB::table('tags')->where('name', $name)->value('id');

                    if ($tagId === null) {
                        $tagId = DB::table('tags')->insertGetId([
                            'name' => $name,
                            'description' => '由旧对手资料迁移的普通标签',
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    DB::table('opponent_photo')
                        ->where('opponent_id', $opponent->id)
                        ->pluck('photo_id')
                        ->each(function (int $photoId) use ($tagId): void {
                            DB::table('photo_tag')->insertOrIgnore([
                                'photo_id' => $photoId,
                                'tag_id' => $tagId,
                            ]);
                        });
                });
        }

        if (Schema::hasColumn('photos', 'source_id')) {
            Schema::table('photos', function (Blueprint $table): void {
                $table->dropForeign(['source_id']);
                $table->dropIndex('photos_source_id_status_index');
                $table->dropColumn('source_id');
            });
        }

        if (Schema::hasColumn('tags', 'type')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->dropIndex('tags_type_sort_order_index');
                $table->dropColumn('type');
            });
        }

        Schema::dropIfExists('opponent_photo');
        Schema::dropIfExists('opponents');
        Schema::dropIfExists('sources');
    }

    public function down(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('original_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('copyright_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->index(['is_enabled', 'published_at']);
        });

        Schema::table('photos', function (Blueprint $table): void {
            $table->foreignId('source_id')->nullable()->after('event_date')->constrained('sources')->nullOnDelete();
            $table->index(['source_id', 'status']);
        });

        Schema::table('tags', function (Blueprint $table): void {
            $table->string('type')->default('普通');
            $table->index(['type', 'sort_order']);
        });

        Schema::create('opponents', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country')->nullable();
            $table->text('aliases')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('opponent_photo', function (Blueprint $table): void {
            $table->foreignId('opponent_id')->constrained('opponents')->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->primary(['opponent_id', 'photo_id']);
            $table->index(['photo_id', 'opponent_id']);
        });
    }
};
