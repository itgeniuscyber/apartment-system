<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('lease_number', 40)->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('rent_amount');
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->unsignedTinyInteger('due_day')->default(1);
            $table->unsignedTinyInteger('grace_days')->default(0);
            $table->string('billing_frequency', 20)->default('monthly');
            $table->string('status', 30)->default('draft');
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
