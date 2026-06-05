<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_rate', function (Blueprint $table) {
            $table->foreignId('coordinator_id')->nullable()->constrained('users');
            $table->decimal('coordinator_amount', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_rate', function (Blueprint $table) {
            $table->dropForeign(['coordinator_id']);
            $table->dropColumn(['coordinator_id', 'coordinator_amount']);
        });
    }
};
