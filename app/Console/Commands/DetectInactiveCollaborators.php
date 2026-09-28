<?php

namespace App\Console\Commands;

use App\Services\Rh\InactiveCollaboratorDetector;
use Illuminate\Console\Command;

class DetectInactiveCollaborators extends Command
{
    protected $signature = 'rh:detect-inactive-collaborators';

    protected $description = 'Auditoria de inatividade: alerta aos 18 dias e processo aos 25 dias';

    public function handle(InactiveCollaboratorDetector $detector): int
    {
        $created = $detector->detect();
        $this->info("Cards de inatividade criados/atualizados: {$created}");

        return self::SUCCESS;
    }
}
