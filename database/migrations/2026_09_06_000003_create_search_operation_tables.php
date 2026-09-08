<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword', 120);
            $table->string('normalized_keyword', 120)->index();
            $table->string('source', 50)->default('search_page')->index();
            $table->unsignedInteger('result_count')->default(0)->index();
            $table->json('filters_json')->nullable();
            $table->timestamps();

            $table->index(['normalized_keyword', 'result_count']);
            $table->index(['created_at', 'normalized_keyword']);
        });

        Schema::create('search_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword', 120);
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_recommendations');
        Schema::dropIfExists('search_queries');
    }
};
