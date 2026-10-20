<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('reuse_key', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type', 16);
            $table->string('target_key', 180);
            $table->string('channel', 24);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('operations')->default(0);
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->string('poster_status', 16)->default('none');
            $table->string('poster_path')->nullable();
            $table->string('poster_fingerprint', 64)->nullable();
            $table->string('poster_error')->nullable();
            $table->timestamp('poster_requested_at')->nullable();
            $table->timestamps();
        });
        Schema::create('share_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_link_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('valid')->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('share_identities', function (Blueprint $table) {
            $table->string('identity', 64)->primary();
            $table->foreignId('share_referral_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('share_known_users', function (Blueprint $table) {
            $table->string('identity', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['identity', 'user_id']);
        });
        Schema::create('share_link_audiences', function (Blueprint $table) {
            $table->foreignId('share_link_id')->constrained()->restrictOnDelete();
            $table->string('identity', 64);
            $table->primary(['share_link_id', 'identity']);
        });
        Schema::create('share_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('share_link_id')->constrained()->restrictOnDelete();
            $table->string('visitor_hash', 64);
            $table->string('account_hash', 64)->nullable();
            $table->string('session_hash', 64);
            $table->string('ip_hash', 64);
            $table->string('receipt_hash', 64);
            $table->unsignedInteger('visible_seconds')->default(0);
            $table->timestamp('last_tick_at');
            $table->string('status', 24)->default('pending')->index();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['share_link_id', 'visitor_hash']);
            $table->index('created_at');
        });
        Schema::create('share_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_link_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('share_referral_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32);
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_events');
        Schema::dropIfExists('share_visits');
        Schema::dropIfExists('share_link_audiences');
        Schema::dropIfExists('share_known_users');
        Schema::dropIfExists('share_identities');
        Schema::dropIfExists('share_referrals');
        Schema::dropIfExists('share_links');
    }
};
