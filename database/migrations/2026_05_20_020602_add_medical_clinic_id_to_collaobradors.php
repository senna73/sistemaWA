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
        
        Schema::table('collaborators', function (Blueprint $table) {
            $table->foreignId('examined_medical_clinic_id')
                ->nullable()
                ->constrained('medical_clinics', 'id')
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collaborators', function (Blueprint $table) {
            $table->dropForeign(['examined_medical_clinic_id']);
            
            $table->dropColumn('examined_medical_clinic_id');
        });
    }
};
