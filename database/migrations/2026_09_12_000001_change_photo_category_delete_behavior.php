<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_category', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
        });

        Schema::table('photo_category', function (Blueprint $table): void {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('photo_category', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
        });

        Schema::table('photo_category', function (Blueprint $table): void {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
        });
    }
};