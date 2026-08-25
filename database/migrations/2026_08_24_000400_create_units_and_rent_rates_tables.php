<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('number', 20);
            $table->unsignedBigInteger('rent_amount');
            $table->string('status', 30)->default('setup_required');
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 3, 1)->nullable();
            $table->string('floor')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('unit_rent_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source', 40)->default('admin');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_rent_rates');
        Schema::dropIfExists('units');
    }
};
