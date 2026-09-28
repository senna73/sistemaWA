<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_tasks')) {
            return;
        }

        Schema::create('rh_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('status', 20)->default('pending');
            $table->string('title');
            $table->foreignId('collaborator_id')->constrained('collaborators');
            $table->foreignId('offboarding_process_id')->nullable()->constrained('offboarding_processes');
            $table->timestamp('due_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_tasks');
    }
};
