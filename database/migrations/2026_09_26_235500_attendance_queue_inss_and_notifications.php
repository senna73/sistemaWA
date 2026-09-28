<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offboarding_processes') && ! Schema::hasColumn('offboarding_processes', 'verification_started_at')) {
            Schema::table('offboarding_processes', function (Blueprint $table) {
                $table->timestamp('verification_started_at')->nullable();
            });
        }

        if (Schema::hasTable('candidates')) {
            Schema::table('candidates', function (Blueprint $table) {
                if (! Schema::hasColumn('candidates', 'admission_on')) {
                    $table->date('admission_on')->nullable();
                }
                if (! Schema::hasColumn('candidates', 'inss_registered_at')) {
                    $table->timestamp('inss_registered_at')->nullable();
                }
                if (! Schema::hasColumn('candidates', 'inss_verified_at')) {
                    $table->timestamp('inss_verified_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offboarding_processes') && Schema::hasColumn('offboarding_processes', 'verification_started_at')) {
            Schema::table('offboarding_processes', function (Blueprint $table) {
                $table->dropColumn('verification_started_at');
            });
        }

        if (Schema::hasTable('candidates')) {
            Schema::table('candidates', function (Blueprint $table) {
                foreach (['admission_on', 'inss_registered_at', 'inss_verified_at'] as $column) {
                    if (Schema::hasColumn('candidates', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('notifications');
    }
};
