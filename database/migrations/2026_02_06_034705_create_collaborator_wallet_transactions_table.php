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
        Schema::create('collaborator_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference_id')->unique();
            $table->foreignId('collaborator_wallet_id')->constrained('collaborator_wallet');

            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            
            $table->enum('type', ['credit', 'debit', 'refund', 'adjustment']);
            $table->string('description')->nullable();
            
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborator_wallet_transactions');
    }
};
