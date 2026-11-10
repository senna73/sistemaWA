<?php

namespace App\Console\Commands;

use App\Services\Rh\InactiveCollaboratorDetector;
use Illuminate\Console\Command;

class DetectInactiveCollaborators extends Command
{
    protected $signature = 'rh:detect-inactive-collaborators';

    protected $description = 'Auditoria de inatividade: alerta aos 18 dias. A ida automática para demissão aos 25 dias está pausada até janeiro/2027.';

    public function handle(InactiveCollaboratorDetector $detector): int
    {
        $created = $detector->detect();
        $this->info("Cards de inatividade criados/atualizados: {$created}");

        return self::SUCCESS;
    }
}
