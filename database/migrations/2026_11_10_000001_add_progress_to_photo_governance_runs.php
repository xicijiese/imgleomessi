<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_governance_runs', function (Blueprint $table): void {
            $table->unsignedInteger('total_count')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('photo_governance_runs', function (Blueprint $table): void {
            $table->dropColumn(['total_count', 'processed_count']);
        });
    }
};
