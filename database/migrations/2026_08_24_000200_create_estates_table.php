<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_account_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('name');
            $table->string('code', 24)->unique();
            $table->string('location')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->char('color', 7)->default('#f26745');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estates');
    }
};
