<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->string('type')->default('discussion');
            $table->text('content');
            $table->string('status')->default('pending');
            $table->string('correction_field')->nullable();
            $table->text('suggested_value')->nullable();
            $table->string('evidence_url', 2048)->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('like_count')->default(0);
            $table->timestamps();

            $table->index(['photo_id', 'type', 'status', 'created_at']);
            $table->index(['user_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
