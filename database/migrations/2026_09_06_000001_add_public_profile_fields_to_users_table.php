<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'profile_public')) {
                $table->boolean('profile_public')->default(false)->index()->after('supporter_until');
            }

            if (! Schema::hasColumn('users', 'profile_bio')) {
                $table->string('profile_bio', 240)->nullable()->after('profile_public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'profile_bio')) {
                $table->dropColumn('profile_bio');
            }

            if (Schema::hasColumn('users', 'profile_public')) {
                $table->dropColumn('profile_public');
            }
        });
    }
};
