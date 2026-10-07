<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Console\Command;

class GrantSuperAdmin extends Command
{
    protected $signature = 'users:normalize-roles';

    protected $description = 'Preserva RH/contabilidade/coordenação; o restante vira líder; Dev e Anderson viram super admin';

    public function handle(): int
    {
        AccessControl::normalizeExistingUsers();

        $leaders = User::query()->where('role', 'leader')->count();
        $coordinators = User::query()->where('role', 'coordinator')->count();
        $this->info($leaders.' usuário(s) como líder. '.$coordinators.' coordenador(es) mantido(s).');

        $missing = false;
        foreach (['dev@dev.com' => 'Dev', 'rh.wamerchandising@gmail.com' => 'Anderson'] as $email => $label) {
            $user = User::query()->where('email', $email)->first();
            if (! $user || $user->role !== 'super_admin' || ! $user->can(AccessControl::PERMISSION_SUPER_ADMIN)) {
                $this->error($label.' não ficou como super admin ('.$email.').');
                $missing = true;

                continue;
            }

            $this->info($label.' é super admin: '.$user->name.' <'.$user->email.'>.');
        }

        return $missing ? self::FAILURE : self::SUCCESS;
    }
}
