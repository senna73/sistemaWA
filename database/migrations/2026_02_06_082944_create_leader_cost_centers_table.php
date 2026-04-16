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
        Schema::create('leader_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            
            $table->foreignId('leader_id')->nullable()->constrained('users');
            
            $table->foreignId('company_id')->constrained('companies'); 

            // O saldo acumulado que o líder tem nesse centro de custo
            $table->decimal('balance', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leader_cost_centers');
    }
};
