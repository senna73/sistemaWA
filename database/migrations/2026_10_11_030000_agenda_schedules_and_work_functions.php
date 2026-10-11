<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('work_function', 64)->nullable()->after('role')->index();
        });

        Schema::create('agenda_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('work_function', 64)->index();
            $table->string('recurrence', 32);
            $table->unsignedTinyInteger('anchor_weekday')->nullable();
            $table->unsignedTinyInteger('anchor_day')->nullable();
            $table->string('due_time', 5)->default('18:00');
            $table->string('priority', 20)->default('normal');
            $table->decimal('effort_hours', 5, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('agenda_items', function (Blueprint $table) {
            $table->unsignedBigInteger('assignee_id')->nullable()->change();
            $table->foreignId('agenda_schedule_id')->nullable()->after('parent_id')->constrained('agenda_schedules')->nullOnDelete();
            $table->string('work_function', 64)->nullable()->after('agenda_schedule_id')->index();
            $table->date('occurrence_on')->nullable()->after('work_function');
            $table->decimal('effort_hours', 5, 2)->nullable()->after('priority');
            $table->text('correction_note')->nullable();
            $table->unique(['agenda_schedule_id', 'occurrence_on'], 'agenda_items_schedule_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_items', function (Blueprint $table) {
            $table->dropUnique('agenda_items_schedule_occurrence_unique');
            $table->dropConstrainedForeignId('agenda_schedule_id');
            $table->dropColumn(['work_function', 'occurrence_on', 'effort_hours', 'correction_note']);
            $table->unsignedBigInteger('assignee_id')->nullable(false)->change();
        });

        Schema::dropIfExists('agenda_schedules');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('work_function');
        });
    }
};
