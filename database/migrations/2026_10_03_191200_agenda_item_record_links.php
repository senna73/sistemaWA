<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agenda_items')) {
            Schema::table('agenda_items', function (Blueprint $table) {
                if (! Schema::hasColumn('agenda_items', 'collaborator_id')) {
                    $table->foreignId('collaborator_id')->nullable()->after('parent_id')->constrained('collaborators')->nullOnDelete();
                }
                if (! Schema::hasColumn('agenda_items', 'offboarding_process_id')) {
                    $table->foreignId('offboarding_process_id')->nullable()->constrained('offboarding_processes')->nullOnDelete();
                }
                if (! Schema::hasColumn('agenda_items', 'candidate_id')) {
                    $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
                }
                if (! Schema::hasColumn('agenda_items', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                }
                if (! Schema::hasColumn('agenda_items', 'payload')) {
                    $table->json('payload')->nullable();
                }
            });
        }

        if (Schema::hasTable('operational_demands') && ! Schema::hasColumn('operational_demands', 'payload')) {
            Schema::table('operational_demands', function (Blueprint $table) {
                $table->json('payload')->nullable()->after('request_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operational_demands') && Schema::hasColumn('operational_demands', 'payload')) {
            Schema::table('operational_demands', function (Blueprint $table) {
                $table->dropColumn('payload');
            });
        }

        if (Schema::hasTable('agenda_items')) {
            Schema::table('agenda_items', function (Blueprint $table) {
                foreach (['collaborator_id', 'offboarding_process_id', 'candidate_id', 'company_id'] as $column) {
                    if (Schema::hasColumn('agenda_items', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
                if (Schema::hasColumn('agenda_items', 'payload')) {
                    $table->dropColumn('payload');
                }
            });
        }
    }
};
