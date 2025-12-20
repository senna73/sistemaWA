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
        Schema::create('acordos_valor_extra', function (Blueprint $table) {
            $table->id();
            $table->decimal('value', 15, 2);

            $table->foreignId('collaborator_id')
            ->constrained('collaborators')
            ->onDelete('cascade');
            $table->foreignId('company_id')
            ->constrained('companies')
            ->onDelete('cascade');
            
            $table->boolean('active')->default(true);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acordos_valor_extra');
    }
};
