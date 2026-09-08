<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_analysis_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('photo_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('sha256_hash', 64)->nullable()->index();
            $table->json('exif_json')->nullable();
            $table->longText('ocr_text')->nullable();
            $table->json('ci_labels_json')->nullable();
            $table->json('ci_quality_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('processing_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->index();
            $table->foreignId('photo_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['photo_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_jobs');
        Schema::dropIfExists('photo_analysis_results');
    }
};
