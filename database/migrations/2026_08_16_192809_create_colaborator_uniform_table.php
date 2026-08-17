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
        Schema::create('collaborator_uniforms', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('collaborator_id')
                  ->constrained('collaborators')
                  ->onDelete('cascade');

            $table->foreignId('uniform_type_id')
                  ->constrained('uniform_types')
                  ->onDelete('cascade');

            $table->foreignId('uniform_size_id')
                  ->nullable()
                  ->constrained('uniform_sizes')
                  ->nullOnDelete();

            $table->integer('quantity')->default(1);
            $table->date('delivered_at')->nullable();
            $table->text('observation')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborator_uniforms');
    }
};
