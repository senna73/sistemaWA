<?php

namespace App\Console\Commands;

use App\Services\Rh\ClinicPanelService;
use Illuminate\Console\Command;

class CreateCliomedWeeklyCheck extends Command
{
    protected $signature = 'rh:cliomed-weekly-check';

    protected $description = 'Cria a tarefa semanal Conferir base Cliomed';

    public function handle(ClinicPanelService $clinics): int
    {
        $check = $clinics->ensureWeeklyCheck();
        $this->info('Conferência da semana '.$check->week_of->toDateString().' ('.$check->status.')');

        return self::SUCCESS;
    }
}
