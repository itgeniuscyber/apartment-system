<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role')->index();
            $table->json('permissions')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('permissions');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
        });

        // The administrator who already owns the installation becomes its first Super Admin.
        DB::table('users')->whereIn('role', ['administrator', 'landlord'])->update(['role' => 'super_admin']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'administrator']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'administrator']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['deactivated_by']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['is_active', 'permissions', 'deactivated_at', 'deactivated_by']);
        });
    }
};
