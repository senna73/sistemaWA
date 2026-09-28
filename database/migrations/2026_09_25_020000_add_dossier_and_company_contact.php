<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'contact_email')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('contact_email')->nullable();
            });
        }

        if (Schema::hasTable('offboarding_processes')) {
            Schema::table('offboarding_processes', function (Blueprint $table) {
                if (! Schema::hasColumn('offboarding_processes', 'dossier_path')) {
                    $table->string('dossier_path')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'dossier_archived_at')) {
                    $table->timestamp('dossier_archived_at')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'dossier_retention_until')) {
                    $table->date('dossier_retention_until')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'contact_email')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('contact_email');
            });
        }

        if (Schema::hasTable('offboarding_processes')) {
            Schema::table('offboarding_processes', function (Blueprint $table) {
                foreach (['dossier_path', 'dossier_archived_at', 'dossier_retention_until'] as $column) {
                    if (Schema::hasColumn('offboarding_processes', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
