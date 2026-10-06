<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_demands') || Schema::hasColumn('operational_demands', 'payload')) {
            return;
        }

        Schema::table('operational_demands', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('request_text');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operational_demands') || ! Schema::hasColumn('operational_demands', 'payload')) {
            return;
        }

        Schema::table('operational_demands', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
