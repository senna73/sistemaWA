<?php

namespace App\Console\Commands;

use App\Services\Finance\RestoreUnpaidInssPay as RestoreUnpaidInssPayService;
use Illuminate\Console\Command;

class RestoreUnpaidInssPay extends Command
{
    protected $signature = 'finance:restore-unpaid-inss-pay {--apply : Grava o reajuste. Sem esta opção, só lista.}';

    protected $description = 'Devolve o INSS descontado nas diárias que ainda não foram pagas (DT-001)';

    public function handle(RestoreUnpaidInssPayService $restore): int
    {
        $apply = (bool) $this->option('apply');
        $result = $restore->run($apply);

        if ($result['changed'] === []) {
            $this->info('Nenhuma diária em aberto com INSS descontado.');
            $this->line($result['skipped_paid'].' diária(s) já paga(s) foram deixadas como estão.');

            return self::SUCCESS;
        }

        $this->table(
            ['Diária', 'Colaborador', 'Data', 'De', 'Para', 'INSS devolvido'],
            array_map(fn (array $row) => [
                $row['id'],
                $row['collaborator'],
                $row['start'],
                number_format($row['from'], 2, ',', '.'),
                number_format($row['to'], 2, ',', '.'),
                number_format($row['inss'], 2, ',', '.'),
            ], $result['changed'])
        );

        $total = array_sum(array_column($result['changed'], 'inss'));
        $count = count($result['changed']);

        if ($apply) {
            $this->info($count.' diária(s) reajustada(s). Total devolvido: R$ '.number_format($total, 2, ',', '.').'.');
        } else {
            $this->comment($count.' diária(s) seriam reajustadas. Total: R$ '.number_format($total, 2, ',', '.').'.');
            $this->comment('Nada foi gravado. Rode de novo com --apply para aplicar.');
        }

        $this->line($result['skipped_paid'].' diária(s) já paga(s) foram deixadas como estão.');

        return self::SUCCESS;
    }
}
