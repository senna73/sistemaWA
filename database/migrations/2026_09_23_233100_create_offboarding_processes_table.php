<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offboarding_processes')) {
            return;
        }

        Schema::create('offboarding_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaborator_id')->constrained('collaborators');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->string('origin', 32);
            $table->string('status', 40);
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['collaborator_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_processes');
    }
};
