<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 50);
            $table->string('title');
            $table->text('description');
            $table->string('priority', 24)->default('normal');
            $table->string('status', 30)->default('open');
            $table->boolean('access_permission')->default(false);
            $table->timestamp('preferred_visit_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_original_name')->nullable();
            $table->string('evidence_mime', 100)->nullable();
            $table->unsignedBigInteger('evidence_size')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['unit_id', 'created_at']);
        });

        Schema::create('maintenance_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('status', 30)->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['maintenance_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_updates');
        Schema::dropIfExists('maintenance_requests');
    }
};
