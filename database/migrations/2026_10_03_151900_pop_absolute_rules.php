<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collaborators') && ! Schema::hasColumn('collaborators', 'accounting_code')) {
            Schema::table('collaborators', function (Blueprint $table) {
                $table->string('accounting_code', 20)->nullable()->unique()->after('hired_at');
            });
        }

        if (! Schema::hasTable('accounting_list_checks')) {
            Schema::create('accounting_list_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('original_name')->nullable();
                $table->string('path')->nullable();
                $table->string('source', 20)->default('csv');
                $table->unsignedInteger('row_count')->default(0);
                $table->string('status', 20)->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_list_rows')) {
            Schema::create('accounting_list_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounting_list_check_id')->constrained('accounting_list_checks')->cascadeOnDelete();
                $table->string('code', 20)->nullable();
                $table->string('name');
                $table->date('admission_on')->nullable();
                $table->string('bucket', 40);
                $table->foreignId('collaborator_id')->nullable()->constrained('collaborators')->nullOnDelete();
                $table->json('candidate_ids')->nullable();
                $table->date('wa_hired_on')->nullable();
                $table->string('wa_code', 20)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('resolution', 40)->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('daily_rate_release_requests')) {
            Schema::create('daily_rate_release_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('daily_on');
                $table->unsignedSmallInteger('window_hours')->default(48);
                $table->string('reason');
                $table->string('status', 20)->default('pending');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agenda_items')) {
            Schema::create('agenda_items', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('due_at')->nullable();
                $table->string('priority', 20)->default('normal');
                $table->string('type', 40);
                $table->string('area')->nullable();
                $table->string('status', 30)->default('pending');
                $table->string('recurrence', 40)->default('once');
                $table->foreignId('parent_id')->nullable()->constrained('agenda_items')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('operational_demands')) {
            Schema::create('operational_demands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collaborator_id')->nullable()->constrained('collaborators')->nullOnDelete();
                $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('agenda_item_id')->nullable()->constrained('agenda_items')->nullOnDelete();
                $table->string('name');
                $table->string('mobile')->nullable();
                $table->string('category', 40);
                $table->text('request_text');
                $table->string('status', 30)->default('awaiting');
                $table->boolean('needs_review')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('operational_demand_events')) {
            Schema::create('operational_demand_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operational_demand_id')->constrained('operational_demands')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 40);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('operational_demand_attachments')) {
            Schema::create('operational_demand_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operational_demand_id')->constrained('operational_demands')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_demand_attachments');
        Schema::dropIfExists('operational_demand_events');
        Schema::dropIfExists('operational_demands');
        Schema::dropIfExists('agenda_items');
        Schema::dropIfExists('daily_rate_release_requests');
        Schema::dropIfExists('accounting_list_rows');
        Schema::dropIfExists('accounting_list_checks');

        if (Schema::hasTable('collaborators') && Schema::hasColumn('collaborators', 'accounting_code')) {
            Schema::table('collaborators', function (Blueprint $table) {
                $table->dropUnique(['accounting_code']);
                $table->dropColumn('accounting_code');
            });
        }
    }
};
