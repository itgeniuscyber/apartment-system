<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('identity_document_type', 40)->nullable()->after('alternative_phone');
            $table->text('identity_document_number')->nullable()->after('identity_document_type');
            $table->string('identity_document_last_four', 4)->nullable()->index()->after('identity_document_number');
            $table->string('identity_document_path')->nullable()->after('identity_document_last_four');
            $table->string('identity_document_original_name')->nullable()->after('identity_document_path');
            $table->string('identity_document_mime', 100)->nullable()->after('identity_document_original_name');
            $table->unsignedBigInteger('identity_document_size')->nullable()->after('identity_document_mime');
            $table->timestamp('identity_document_uploaded_at')->nullable()->after('identity_document_size');
            $table->timestamp('identity_verified_at')->nullable()->after('identity_document_uploaded_at');
            $table->foreignId('identity_verified_by')
                ->nullable()
                ->after('identity_verified_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('next_of_kin_name')->nullable()->after('identity_verified_by');
            $table->string('next_of_kin_relationship', 100)->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_phone', 30)->nullable()->after('next_of_kin_relationship');
            $table->string('next_of_kin_email')->nullable()->after('next_of_kin_phone');
            $table->text('next_of_kin_address')->nullable()->after('next_of_kin_email');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['identity_verified_by']);
            $table->dropIndex(['identity_document_last_four']);
            $table->dropColumn([
                'identity_document_type',
                'identity_document_number',
                'identity_document_last_four',
                'identity_document_path',
                'identity_document_original_name',
                'identity_document_mime',
                'identity_document_size',
                'identity_document_uploaded_at',
                'identity_verified_at',
                'identity_verified_by',
                'next_of_kin_name',
                'next_of_kin_relationship',
                'next_of_kin_phone',
                'next_of_kin_email',
                'next_of_kin_address',
            ]);
        });
    }
};
