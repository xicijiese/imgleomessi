<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('role_before_grant')->default('user');
            $table->boolean('role_granted_by_contributor')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_public', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_profiles');
    }
};