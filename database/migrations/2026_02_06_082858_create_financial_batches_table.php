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
        Schema::create('financial_batches', function (Blueprint $table) {
            $table->id();

            // Id da Loja 
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            $table->decimal('total_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2)->default(0.00);
            $table->decimal('cost_center_amount', 15, 2)->default(0.00);
            
            // Datas do período que este lote está cobrindo 
            $table->date('period_start');
            $table->date('period_end');

            $table->date('processed_at')->nullable();

            // Status do processamento do lote
            $table->enum('status', ['pending', 'processing', 'completed', 'canceled'])->default('pending');

            // Use JSON para guardar detalhes extras
            $table->json('metadata')->nullable(); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_batches');
    }
};
