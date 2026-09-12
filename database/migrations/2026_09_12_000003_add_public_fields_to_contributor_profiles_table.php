<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributor_profiles', function (Blueprint $table): void {
            $table->string('contribution_focus')->nullable()->after('bio');
            $table->unsignedInteger('sort_order')->default(0)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('contributor_profiles', function (Blueprint $table): void {
            $table->dropColumn(['contribution_focus', 'sort_order']);
        });
    }
};