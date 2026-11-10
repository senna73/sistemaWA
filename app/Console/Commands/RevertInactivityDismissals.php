<?php

namespace App\Console\Commands;

use App\Services\Rh\InactiveCollaboratorDetector;
use Illuminate\Console\Command;

class RevertInactivityDismissals extends Command
{
    protected $signature = 'rh:revert-inactivity-dismissals';

    protected $description = 'Reverte quem entrou na zona de demissão pelos 25 dias sem diária (débito técnico até janeiro/2027)';

    public function handle(InactiveCollaboratorDetector $detector): int
    {
        $names = $detector->revertAutomaticDismissals();

        if ($names === []) {
            $this->info('Nenhum colaborador na zona de demissão por 25 dias sem diária.');

            return self::SUCCESS;
        }

        foreach ($names as $name) {
            $this->line($name);
        }

        $this->info(count($names).' colaborador(es) revertido(s). A regra volta em janeiro/2027.');

        return self::SUCCESS;
    }
}
