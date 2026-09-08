<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensitive_words', function (Blueprint $table): void {
            $table->id();
            $table->string('word')->unique();
            $table->string('severity')->default('medium');
            $table->boolean('is_enabled')->default(true);
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_words');
    }
};
