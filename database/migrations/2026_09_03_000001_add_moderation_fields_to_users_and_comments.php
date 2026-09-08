<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('status')->default('active')->index();
            });
        }

        if (! Schema::hasColumn('users', 'banned_until')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('banned_until')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('users', 'ban_reason')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('ban_reason')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'moderation_note')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('moderation_note')->nullable();
            });
        }

        if (! Schema::hasColumn('comments', 'reviewed_by')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('comments', 'reviewed_at')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->timestamp('reviewed_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('comments', 'moderation_note')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->text('moderation_note')->nullable();
            });
        }

        if (! Schema::hasColumn('comments', 'risk_level')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->string('risk_level')->default('clean')->index();
            });
        }

        if (! Schema::hasColumn('comments', 'sensitive_word_hits')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->json('sensitive_word_hits')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('comments', 'reviewed_by')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->dropForeign(['reviewed_by']);
            });
        }

        $commentColumns = array_values(array_filter([
            Schema::hasColumn('comments', 'reviewed_by') ? 'reviewed_by' : null,
            Schema::hasColumn('comments', 'reviewed_at') ? 'reviewed_at' : null,
            Schema::hasColumn('comments', 'moderation_note') ? 'moderation_note' : null,
            Schema::hasColumn('comments', 'risk_level') ? 'risk_level' : null,
            Schema::hasColumn('comments', 'sensitive_word_hits') ? 'sensitive_word_hits' : null,
        ]));

        if ($commentColumns !== []) {
            Schema::table('comments', function (Blueprint $table) use ($commentColumns): void {
                $table->dropColumn($commentColumns);
            });
        }

        $userColumns = array_values(array_filter([
            Schema::hasColumn('users', 'status') ? 'status' : null,
            Schema::hasColumn('users', 'banned_until') ? 'banned_until' : null,
            Schema::hasColumn('users', 'ban_reason') ? 'ban_reason' : null,
            Schema::hasColumn('users', 'moderation_note') ? 'moderation_note' : null,
        ]));

        if ($userColumns !== []) {
            Schema::table('users', function (Blueprint $table) use ($userColumns): void {
                $table->dropColumn($userColumns);
            });
        }
    }
};
