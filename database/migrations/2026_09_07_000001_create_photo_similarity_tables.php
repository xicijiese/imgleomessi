<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_analysis_results', function (Blueprint $table): void {
            $table->string('perceptual_hash', 16)->nullable()->index()->after('sha256_hash');
        });

        Schema::create('photo_similarity_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_photo_id')->constrained('photos')->cascadeOnDelete();
            $table->unsignedTinyInteger('distance');
            $table->decimal('similarity_score', 5, 2);
            $table->string('status')->default('pending_review')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['photo_id', 'candidate_photo_id']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_similarity_candidates');

        Schema::table('photo_analysis_results', function (Blueprint $table): void {
            $table->dropIndex(['perceptual_hash']);
            $table->dropColumn('perceptual_hash');
        });
    }
};