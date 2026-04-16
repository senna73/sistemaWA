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
        // Livro razão que guardará as movimentações financeiras
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_batch_id')->nullable()->constrained('financial_batches');
            
            // Nullable porque um registro pode ser um crédito geral, não necessariamente para um usuário
            $table->foreignId('user_id')->nullable()->index()->constrained('users'); 
            $table->foreignId('collaborator_wallet_id')->nullable()->index()->constrained('collaborator_wallet'); 
            $table->foreignId('cost_center_id')->nullable()->index()->constrained('leader_cost_centers');
            
            // Valores e Auditoria de Saldo
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            
            // Dados para reversão
            $table->boolean('is_reversed')->default(false);
            $table->date('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->foreignId('reversal_ledger_id')
                ->nullable()
                ->constrained('ledgers')
                ->onDelete('set null');

            
            $table->enum('entry_type', ['credit', 'debit']);
            
            // Tipagem e Classificação
            $table->string('category')->index();
            $table->string('description');
            $table->json('metadata')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
