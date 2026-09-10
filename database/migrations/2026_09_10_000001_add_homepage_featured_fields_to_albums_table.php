<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('albums', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('status');
            $table->timestamp('featured_at')->nullable()->after('is_featured');
            $table->index(['is_featured', 'featured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table): void {
            $table->dropIndex('albums_is_featured_featured_at_index');
            $table->dropColumn(['is_featured', 'featured_at']);
        });
    }
};