<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table): void {
            $table->string('image_path')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
        });
        Schema::create('badge_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_badge_id')->constrained()->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->text('note')->nullable();
            $table->timestamps();
        });
        // 旧版没有完整事件流水，只按现存记录补入迁移基线，不虚构历史操作人。
        DB::table('badge_events')->insertUsing(
            ['user_badge_id', 'action', 'note', 'created_at', 'updated_at'],
            DB::table('user_badges')->selectRaw("id, 'awarded', '旧版记录迁移基线', coalesce(awarded_at, created_at), coalesce(awarded_at, created_at)"),
        );
        DB::table('badge_events')->insertUsing(
            ['user_badge_id', 'action', 'note', 'created_at', 'updated_at'],
            DB::table('user_badges')->where('status', 'revoked')->selectRaw("id, 'revoked', '旧版撤销状态迁移基线', coalesce(revoked_at, updated_at), coalesce(revoked_at, updated_at)"),
        );
        Schema::create('badge_grant_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('badge_id')->constrained()->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->text('note')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('badge_grant_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('badge_grant_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unique(['batch_id', 'user_id']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_grant_recipients');
        Schema::dropIfExists('badge_grant_batches');
        Schema::dropIfExists('badge_events');
        Schema::table('badges', fn (Blueprint $table) => $table->dropColumn(['image_path', 'starts_on', 'ends_on']));
    }
};
