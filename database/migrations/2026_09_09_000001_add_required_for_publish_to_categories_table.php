<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('required_for_publish')->default(false)->after('is_system');
        });

        DB::table('categories')
            ->whereNull('parent_id')
            ->whereIn('slug', ['career-stage', 'year', 'scene'])
            ->update(['required_for_publish' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('required_for_publish');
        });
    }
};