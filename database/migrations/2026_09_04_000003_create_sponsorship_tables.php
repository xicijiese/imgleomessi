<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('badge_level')->default('supporter');
            $table->text('benefits')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });

        Schema::create('supporter_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->boolean('show_publicly')->default(true)->index();
            $table->unsignedInteger('total_amount_cents')->default(0);
            $table->string('badge_level')->default('supporter')->index();
            $table->timestamp('last_supported_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('sponsorship_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsorship_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('channel')->default('mock')->index();
            $table->string('status')->default('pending')->index();
            $table->string('transaction_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('raw_callback_json')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsorship_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('mock')->index();
            $table->string('event_type')->index();
            $table->string('status')->default('received')->index();
            $table->json('payload')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('sponsorship_orders');
        Schema::dropIfExists('supporter_profiles');
        Schema::dropIfExists('sponsorship_plans');
    }
};
