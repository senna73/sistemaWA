<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'mobile')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('mobile', 20)->nullable()->after('email');
            });
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(32) NOT NULL DEFAULT 'employee'");
        } elseif (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 32)->default('employee')->change();
            });
        }

        $this->detachDuplicateCollaboratorLinks();

        if (! $this->hasUniqueCollaboratorIndex()) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('collaborator_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasUniqueCollaboratorIndex()) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['collaborator_id']);
            });
        }

        if (Schema::hasColumn('users', 'mobile')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('mobile');
            });
        }
    }

    /**
     * Keep the oldest user per collaborator_id; clear the rest so the unique index can be created.
     */
    private function detachDuplicateCollaboratorLinks(): void
    {
        $duplicates = DB::table('users')
            ->select('collaborator_id')
            ->whereNotNull('collaborator_id')
            ->groupBy('collaborator_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('collaborator_id');

        foreach ($duplicates as $collaboratorId) {
            $keepId = DB::table('users')
                ->where('collaborator_id', $collaboratorId)
                ->orderBy('id')
                ->value('id');

            DB::table('users')
                ->where('collaborator_id', $collaboratorId)
                ->where('id', '!=', $keepId)
                ->update(['collaborator_id' => null]);
        }
    }

    private function hasUniqueCollaboratorIndex(): bool
    {
        $indexes = Schema::getIndexes('users');

        foreach ($indexes as $index) {
            $columns = $index['columns'] ?? [];
            $unique = $index['unique'] ?? false;
            $name = $index['name'] ?? '';

            if ($unique && $columns === ['collaborator_id']) {
                return true;
            }

            if ($name === 'users_collaborator_id_unique') {
                return true;
            }
        }

        return false;
    }
};
