<?php

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        AccessControl::seed();
        AccessControl::ensureBootstrapSuperAdmin();
    }

    public function down(): void
    {
        $permission = Permission::query()
            ->where('name', AccessControl::PERMISSION_SUPER_ADMIN)
            ->first();

        if (! $permission) {
            return;
        }

        User::permission(AccessControl::PERMISSION_SUPER_ADMIN)
            ->get()
            ->each(fn (User $user) => $user->revokePermissionTo($permission));

        $permission->delete();
    }
};
