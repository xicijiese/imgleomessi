<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorship_orders', function (Blueprint $table): void {
            $table->json('plan_snapshot')->nullable();
            $table->text('gateway_credentials')->nullable();
            $table->string('gateway_transaction')->nullable()->unique();
            $table->string('platform_order_id')->nullable();
            $table->text('payment_url')->nullable();
            $table->text('qr_url')->nullable();
            $table->timestamp('qr_expires_at')->nullable();
            $table->string('refund_state')->nullable();
            $table->string('refund_no')->nullable();
            $table->text('refund_reason')->nullable();
            $table->foreignId('refund_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refund_requested_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('payment_issue')->nullable();
        });
        Schema::create('sponsorship_accounts', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamp('legacy_until')->nullable();
            $table->boolean('legacy_permanent')->default(false);
            $table->json('legacy_profile')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsorship_order_id')->constrained()->cascadeOnDelete();
            $table->string('operation');
            $table->string('status');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('sponsorship_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('days');
            $table->timestamps();
            $table->unique(['user_id', 'expires_at', 'days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_reminders');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('sponsorship_accounts');
        Schema::table('sponsorship_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refund_requested_by');
            $table->dropColumn(['plan_snapshot', 'gateway_credentials', 'gateway_transaction', 'platform_order_id', 'payment_url', 'qr_url', 'qr_expires_at', 'refund_state', 'refund_no', 'refund_reason', 'refund_requested_at', 'checked_at', 'payment_issue']);
        });
    }
};
