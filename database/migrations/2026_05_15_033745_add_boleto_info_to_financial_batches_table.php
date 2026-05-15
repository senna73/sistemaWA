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
        Schema::table('financial_batches', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('id');
            $table->text('description')->nullable()->after('invoice_number');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_batches', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'description']);
        });
    }
};
