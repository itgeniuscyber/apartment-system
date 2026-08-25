<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body');
            $table->string('severity', 24)->default('info');
            $table->string('audience_type', 24)->default('all');
            $table->foreignId('estate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->json('channels');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['published_at', 'severity']);
        });

        Schema::create('notice_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['notice_id', 'tenant_id']);
            $table->index(['tenant_id', 'read_at']);
        });

        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('subject');
            $table->string('category', 40)->default('general');
            $table->string('status', 24)->default('open');
            $table->timestamp('last_message_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
            $table->index(['tenant_id', 'last_message_at']);
        });

        Schema::create('thread_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 24);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['message_thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_messages');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('notice_recipients');
        Schema::dropIfExists('notices');
    }
};
