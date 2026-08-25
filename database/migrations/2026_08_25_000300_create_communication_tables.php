<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->boolean('sms_enabled')->default(false);
            $table->string('sms_provider', 40)->default('africas_talking');
            $table->string('sms_sender_id', 30)->nullable();
            $table->string('sms_username')->nullable();
            $table->text('sms_api_key')->nullable();
            $table->text('sms_webhook_url')->nullable();
            $table->text('sms_webhook_token')->nullable();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_provider', 40)->default('meta_cloud');
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->string('whatsapp_api_version', 20)->default('v23.0');
            $table->string('whatsapp_notice_template')->nullable();
            $table->string('whatsapp_invoice_template')->nullable();
            $table->string('whatsapp_receipt_template')->nullable();
            $table->string('whatsapp_reminder_template')->nullable();
            $table->json('invoice_channels')->nullable();
            $table->json('receipt_channels')->nullable();
            $table->json('reminder_channels')->nullable();
            $table->json('reminder_days_before')->nullable();
            $table->unsignedTinyInteger('overdue_repeat_days')->default(3);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('communication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable()->index();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_type', 60);
            $table->string('reference')->nullable()->index();
            $table->string('channel', 20);
            $table->string('provider', 60)->nullable();
            $table->string('recipient_masked')->nullable();
            $table->string('status', 24)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('tenant_notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 60);
            $table->string('reference', 120);
            $table->date('scheduled_for');
            $table->timestamps();

            $table->unique(['tenant_id', 'event_type', 'reference', 'scheduled_for'], 'tenant_notification_event_unique');
        });

        DB::table('communication_settings')->insert([
            'in_app_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'sms_provider' => 'africas_talking',
            'whatsapp_enabled' => false,
            'whatsapp_provider' => 'meta_cloud',
            'whatsapp_api_version' => 'v23.0',
            'invoice_channels' => json_encode(['database', 'email']),
            'receipt_channels' => json_encode(['database', 'email']),
            'reminder_channels' => json_encode(['database', 'email']),
            'reminder_days_before' => json_encode([7, 3, 0]),
            'overdue_repeat_days' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_notification_events');
        Schema::dropIfExists('communication_deliveries');
        Schema::dropIfExists('communication_settings');
        Schema::dropIfExists('notifications');
    }
};
