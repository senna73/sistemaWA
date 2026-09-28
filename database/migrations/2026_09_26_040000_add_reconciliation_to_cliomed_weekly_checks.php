<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cliomed_weekly_checks')) {
            return;
        }

        Schema::table('cliomed_weekly_checks', function (Blueprint $table) {
            if (! Schema::hasColumn('cliomed_weekly_checks', 'reconciliation')) {
                $table->json('reconciliation')->nullable()->after('attachment_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cliomed_weekly_checks')) {
            return;
        }

        Schema::table('cliomed_weekly_checks', function (Blueprint $table) {
            if (Schema::hasColumn('cliomed_weekly_checks', 'reconciliation')) {
                $table->dropColumn('reconciliation');
            }
        });
    }
};
