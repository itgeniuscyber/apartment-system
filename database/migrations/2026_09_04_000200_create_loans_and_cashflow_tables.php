<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('direction', 20); // borrowed or issued
            $table->string('name');
            $table->string('counterparty_name');
            $table->string('counterparty_phone', 30)->nullable();
            $table->string('counterparty_email')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('principal_amount');
            $table->unsignedBigInteger('total_payable');
            $table->decimal('interest_rate', 8, 3)->nullable();
            $table->unsignedBigInteger('installment_amount')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->string('repayment_frequency', 24)->default('monthly');
            $table->date('start_date');
            $table->date('next_due_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->unsignedTinyInteger('reminder_days_before')->default(5);
            $table->date('last_reminded_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'status']);
            $table->index(['status', 'next_due_date']);
        });

        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('payment_method', 40)->default('bank_transfer');
            $table->string('reference', 120)->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'paid_on']);
        });

        Schema::create('cashflow_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('type', 20); // expense or revenue
            $table->string('category', 60);
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('UGX');
            $table->date('occurred_on');
            $table->foreignId('estate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('settlement_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('counterparty')->nullable();
            $table->string('payment_method', 40)->default('cash');
            $table->string('external_reference', 120)->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence', 24)->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('receipt_mime', 100)->nullable();
            $table->unsignedBigInteger('receipt_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'occurred_on']);
            $table->index(['estate_id', 'occurred_on']);
            $table->index(['created_by', 'occurred_on']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('cashflow_entries');
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loans');
    }
};
