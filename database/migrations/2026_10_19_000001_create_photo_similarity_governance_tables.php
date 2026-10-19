<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_governance_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedBigInteger('last_photo_id')->default(0);
            $table->unsignedBigInteger('max_photo_id')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('operated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('photo_similarity_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('representative_photo_id')->constrained('photos')->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('photo_similarity_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('photo_similarity_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_id')->unique()->constrained()->cascadeOnDelete();
        });
        Schema::create('photo_deduplication_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('photo_id')->constrained()->restrictOnDelete();
            $table->foreignId('retained_photo_id')->constrained('photos')->restrictOnDelete();
            $table->string('sha256_hash', 64);
            $table->string('previous_status', 20);
            $table->foreignId('operated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
        });
        Schema::create('photo_image_searches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('session_hash', 64)->index();
            $table->string('status', 20)->default('pending');
            $table->string('temporary_path')->nullable();
            $table->json('matches')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_image_searches');
        Schema::dropIfExists('photo_deduplication_logs');
        Schema::dropIfExists('photo_similarity_group_members');
        Schema::dropIfExists('photo_similarity_groups');
        Schema::dropIfExists('photo_governance_runs');
    }
};
