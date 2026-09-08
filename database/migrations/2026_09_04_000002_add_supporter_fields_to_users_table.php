<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'supporter_until')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('supporter_until')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'supporter_until')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('supporter_until');
            });
        }
    }
};
