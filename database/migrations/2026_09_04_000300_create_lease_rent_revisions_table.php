<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->unsignedBigInteger('initial_rent_amount')->nullable()->after('rent_amount');
        });

        DB::table('leases')->update(['initial_rent_amount' => DB::raw('rent_amount')]);

        Schema::create('lease_rent_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('previous_amount');
            $table->unsignedBigInteger('amount');
            $table->date('effective_from');
            $table->string('reason', 40);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('pending');
            $table->boolean('notify_tenant')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['lease_id', 'status', 'effective_from']);
            $table->index(['status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_rent_revisions');

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('initial_rent_amount');
        });
    }
};
