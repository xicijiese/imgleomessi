<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('photo_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('asset_uuid')->unique();
            $table->uuid('batch_id')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('uploading')->index();
            $table->string('original_filename');
            $table->string('mime_type', 40);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('sha256', 64)->index();
            $table->string('perceptual_hash', 16)->nullable();
            $table->json('similar_photo_ids')->nullable();
            $table->string('storage_driver', 16);
            $table->json('storage_location')->nullable();
            $table->json('import_location')->nullable();
            $table->string('temporary_key');
            $table->string('version_id')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('show_credit')->default(false);
            $table->foreignId('photo_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('replacement_photo_id')->nullable()->constrained('photos')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('contribution_revoked_at')->nullable();
            $table->timestamp('cleanup_at')->nullable()->index();
            $table->timestamp('cleaned_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'submitted_at']);
        });
        Schema::create('submission_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('submission_id')->nullable();
            $table->foreign('submission_id')->references('id')->on('photo_submissions')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('submission_applications')->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
        Schema::create('photo_submission_claims', function (Blueprint $table): void {
            $table->string('sha256', 64)->primary();
            $table->uuid('submission_id')->unique();
            $table->foreign('submission_id')->references('id')->on('photo_submissions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_submission_claims');
        Schema::dropIfExists('submission_events');
        Schema::dropIfExists('photo_submissions');
        Schema::dropIfExists('submission_applications');
    }
};
