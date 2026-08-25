<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_account_id')->constrained()->restrictOnDelete();
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->char('token_last_eight', 8);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 24)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 60);
            $table->string('payment_method', 40);
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 30)->default('initiated');
            $table->string('provider_reference', 120)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->text('failure_message')->nullable();
            $table->text('metadata')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['provider', 'provider_reference']);
        });

        Schema::create('gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 60);
            $table->string('event_id', 160);
            $table->char('payload_hash', 64);
            $table->text('payload');
            $table->string('status', 24)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_attempt_id')
                ->nullable()
                ->after('settlement_account_id')
                ->unique()
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_attempt_id');
        });

        Schema::dropIfExists('gateway_webhook_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_links');
    }
};
