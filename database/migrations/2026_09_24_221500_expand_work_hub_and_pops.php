<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collaborators') && ! Schema::hasColumn('collaborators', 'hired_at')) {
            Schema::table('collaborators', function (Blueprint $table) {
                $table->timestamp('hired_at')->nullable();
                $table->string('job_title')->nullable();
                $table->foreignId('home_company_id')->nullable()->constrained('companies')->nullOnDelete();
            });

            DB::table('collaborators')->whereNull('hired_at')->update([
                'hired_at' => DB::raw('created_at'),
            ]);
        }

        if (Schema::hasTable('offboarding_processes')) {
            Schema::table('offboarding_processes', function (Blueprint $table) {
                if (! Schema::hasColumn('offboarding_processes', 'kind')) {
                    $table->string('kind', 40)->default('dismissal');
                }
                if (! Schema::hasColumn('offboarding_processes', 'reason')) {
                    $table->string('reason')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'last_work_day')) {
                    $table->date('last_work_day')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'letter_status')) {
                    $table->string('letter_status', 20)->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'wa_daily_count')) {
                    $table->unsignedInteger('wa_daily_count')->default(0);
                }
                if (! Schema::hasColumn('offboarding_processes', 'inss_daily_count')) {
                    $table->unsignedInteger('inss_daily_count')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'accounting_registered')) {
                    $table->boolean('accounting_registered')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'last_exam_clinic')) {
                    $table->string('last_exam_clinic', 40)->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'tenure_days')) {
                    $table->unsignedInteger('tenure_days')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'direction_lock_conserta')) {
                    $table->boolean('direction_lock_conserta')->default(false);
                }
                if (! Schema::hasColumn('offboarding_processes', 'direction_released_at')) {
                    $table->timestamp('direction_released_at')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'authorized_daily_correction')) {
                    $table->unsignedInteger('authorized_daily_correction')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'docs_received')) {
                    $table->boolean('docs_received')->default(false);
                }
                if (! Schema::hasColumn('offboarding_processes', 'docs_checked')) {
                    $table->boolean('docs_checked')->default(false);
                }
                if (! Schema::hasColumn('offboarding_processes', 'deactivated_at')) {
                    $table->timestamp('deactivated_at')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'blocks_daily_rates')) {
                    $table->boolean('blocks_daily_rates')->default(false);
                }
                if (! Schema::hasColumn('offboarding_processes', 'mail_tracking')) {
                    $table->string('mail_tracking')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'exam_at')) {
                    $table->timestamp('exam_at')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'exam_location')) {
                    $table->string('exam_location')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'exam_status')) {
                    $table->string('exam_status', 30)->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'new_company_id')) {
                    $table->foreignId('new_company_id')->nullable()->constrained('companies')->nullOnDelete();
                }
                if (! Schema::hasColumn('offboarding_processes', 'new_role')) {
                    $table->string('new_role')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'transfer_start_date')) {
                    $table->date('transfer_start_date')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'offered_stores')) {
                    $table->text('offered_stores')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'collaborator_reply')) {
                    $table->text('collaborator_reply')->nullable();
                }
                if (! Schema::hasColumn('offboarding_processes', 'accounting_sent_at')) {
                    $table->timestamp('accounting_sent_at')->nullable();
                }
            });

            DB::table('offboarding_processes')->where('status', 'aberto')->update(['status' => 'atendimento_rh']);
            DB::table('offboarding_processes')->where('status', 'realocacao')->update(['status' => 'transferencia_analise', 'kind' => 'transfer']);
            DB::table('offboarding_processes')->where('status', 'demissao')->update(['status' => 'aguardando_documentacao', 'blocks_daily_rates' => true]);
            DB::table('offboarding_processes')->where('status', 'concluido_realocado')->update(['status' => 'transferencia_concluida', 'kind' => 'transfer']);
            DB::table('offboarding_processes')->where('status', 'concluido_demitido')->update(['status' => 'desligado_concluido', 'blocks_daily_rates' => true]);
            DB::table('offboarding_processes')->where('origin', 'collaborator')->where('kind', 'dismissal')->update(['kind' => 'collaborator_resignation']);
        }

        if (Schema::hasTable('offboarding_status_events') && ! Schema::hasColumn('offboarding_status_events', 'event_type')) {
            Schema::table('offboarding_status_events', function (Blueprint $table) {
                $table->string('event_type', 40)->default('status');
                $table->json('payload')->nullable();
            });
        }

        if (! Schema::hasTable('inactivity_audits')) {
            Schema::create('inactivity_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
                $table->string('status', 30)->default('open_18');
                $table->unsignedInteger('days_without_daily')->default(0);
                $table->timestamp('last_daily_at')->nullable();
                $table->string('coordinator_response', 40)->nullable();
                $table->text('coordinator_notes')->nullable();
                $table->date('scale_date')->nullable();
                $table->string('scale_store')->nullable();
                $table->string('scale_role')->nullable();
                $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('responded_at')->nullable();
                $table->text('collaborator_reply')->nullable();
                $table->foreignId('offboarding_process_id')->nullable()->constrained('offboarding_processes')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->index(['collaborator_id', 'status']);
            });
        }

        if (! Schema::hasTable('process_attachments')) {
            Schema::create('process_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offboarding_process_id')->nullable()->constrained('offboarding_processes')->cascadeOnDelete();
                $table->foreignId('inactivity_audit_id')->nullable()->constrained('inactivity_audits')->nullOnDelete();
                $table->string('kind', 40);
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inactivity_allowances')) {
            Schema::create('inactivity_allowances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
                $table->foreignId('inactivity_audit_id')->nullable()->constrained('inactivity_audits')->nullOnDelete();
                $table->string('reason', 40);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('evidence_path')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clinic_pending_cards')) {
            Schema::create('clinic_pending_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collaborator_id')->constrained('collaborators')->cascadeOnDelete();
                $table->string('status', 30)->default('open');
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->unique('collaborator_id');
            });
        }

        if (! Schema::hasTable('clinic_price_table')) {
            Schema::create('clinic_price_table', function (Blueprint $table) {
                $table->id();
                $table->string('city');
                $table->string('clinic', 40)->default('cliomed');
                $table->string('category', 40);
                $table->decimal('amount', 10, 2);
                $table->date('effective_from')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rh_cost_entries')) {
            Schema::create('rh_cost_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offboarding_process_id')->nullable()->constrained('offboarding_processes')->nullOnDelete();
                $table->foreignId('collaborator_id')->nullable()->constrained('collaborators')->nullOnDelete();
                $table->string('category', 40);
                $table->string('city')->nullable();
                $table->decimal('expected_amount', 10, 2)->default(0);
                $table->decimal('actual_amount', 10, 2)->nullable();
                $table->decimal('paid_amount', 10, 2)->nullable();
                $table->string('status', 20)->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cliomed_weekly_checks')) {
            Schema::create('cliomed_weekly_checks', function (Blueprint $table) {
                $table->id();
                $table->date('week_of');
                $table->unsignedInteger('wa_count')->default(0);
                $table->unsignedInteger('report_count')->nullable();
                $table->string('status', 20)->default('open');
                $table->text('notes')->nullable();
                $table->string('attachment_path')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique('week_of');
            });
        }

        if (! Schema::hasTable('vacancies')) {
            Schema::create('vacancies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->string('title');
                $table->unsignedInteger('openings')->default(1);
                $table->string('status', 20)->default('open');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('candidates')) {
            Schema::create('candidates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vacancy_id')->nullable()->constrained('vacancies')->nullOnDelete();
                $table->string('name');
                $table->string('mobile')->nullable();
                $table->string('status', 20)->default('novo');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('vacancies');
        Schema::dropIfExists('cliomed_weekly_checks');
        Schema::dropIfExists('rh_cost_entries');
        Schema::dropIfExists('clinic_price_table');
        Schema::dropIfExists('clinic_pending_cards');
        Schema::dropIfExists('inactivity_allowances');
        Schema::dropIfExists('process_attachments');
        Schema::dropIfExists('inactivity_audits');
    }
};
