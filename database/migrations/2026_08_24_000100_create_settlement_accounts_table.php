<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('owner_name');
            $table->string('bank_name');
            $table->char('account_last_four', 4);
            $table->text('account_number')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_accounts');
    }
};
