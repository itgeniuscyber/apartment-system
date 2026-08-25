<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 24);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('default_rent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['estate_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
