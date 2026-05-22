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
        Schema::create('financial_batch_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_batch_id')->constrained('financial_batches')->onDelete('cascade');
            $table->string('invoice_number');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->boolean('received')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_batche_invoices');
    }
};
