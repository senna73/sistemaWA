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
        Schema::create('cost_leader_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('cost_id')
                ->constrained('costs')
                ->onDelete('cascade');
            $table->enum('status', ['pendente', 'rejeitado', 'aceito']);
            $table->decimal('divided_value', 15, 2); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_leader_shares');
    }
};
