<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('portal_invited_at')->nullable()->after('user_id');
            $table->timestamp('portal_activated_at')->nullable()->after('portal_invited_at');
        });

        DB::table('tenants')
            ->whereNotNull('user_id')
            ->whereNull('portal_activated_at')
            ->update(['portal_activated_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['portal_invited_at', 'portal_activated_at']);
        });
    }
};
