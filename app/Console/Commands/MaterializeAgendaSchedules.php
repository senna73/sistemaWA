<?php

namespace App\Console\Commands;

use App\Services\Rh\AgendaService;
use Illuminate\Console\Command;

class MaterializeAgendaSchedules extends Command
{
    protected $signature = 'rh:materialize-agenda-schedules';

    protected $description = 'Cria as atividades das agendas quando o prazo entra na janela de 7 dias';

    public function handle(AgendaService $agenda): int
    {
        $created = $agenda->materialize();
        $this->info($created.' atividade(s) criada(s) a partir das agendas.');

        return self::SUCCESS;
    }
}
