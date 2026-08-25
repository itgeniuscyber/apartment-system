<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_account_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('description');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('balance_due');
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 30)->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lease_id', 'billing_period_start']);
            $table->index(['status', 'due_date']);
            $table->index(['settlement_account_id', 'due_date']);
            $table->index(['tenant_id', 'due_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_account_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 40)->unique();
            $table->string('receipt_number', 40)->unique();
            $table->string('payment_method', 40);
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('UGX');
            $table->timestamp('paid_at');
            $table->string('status', 30)->default('confirmed');
            $table->string('payer_phone', 30)->nullable();
            $table->string('payer_email')->nullable();
            $table->string('card_brand', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['settlement_account_id', 'paid_at']);
            $table->index(['tenant_id', 'paid_at']);
            $table->index(['status', 'paid_at']);
            $table->unique(['payment_method', 'provider_reference']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
