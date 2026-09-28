<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'headcount_quota')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->unsignedInteger('headcount_quota')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'headcount_quota')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('headcount_quota');
            });
        }
    }
};
