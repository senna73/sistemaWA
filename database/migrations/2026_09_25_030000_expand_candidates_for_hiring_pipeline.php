<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            if (! Schema::hasColumn('candidates', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('vacancy_id')->constrained('companies')->nullOnDelete();
            }
            if (! Schema::hasColumn('candidates', 'job_title')) {
                $table->string('job_title')->nullable()->after('name');
            }
        });

        Schema::table('process_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('process_attachments', 'candidate_id')) {
                $table->foreignId('candidate_id')->nullable()->after('inactivity_audit_id')->constrained('candidates')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('process_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('process_attachments', 'candidate_id')) {
                $table->dropConstrainedForeignId('candidate_id');
            }
        });

        Schema::table('candidates', function (Blueprint $table) {
            if (Schema::hasColumn('candidates', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
            if (Schema::hasColumn('candidates', 'job_title')) {
                $table->dropColumn('job_title');
            }
        });
    }
};
