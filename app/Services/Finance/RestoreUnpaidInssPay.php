<?php

namespace App\Services\Finance;

use App\Models\DailyRate;
use App\Models\FinancialBatches;
use Illuminate\Support\Facades\DB;

class RestoreUnpaidInssPay
{
    /**
     * Devolve o INSS gravado na diária para o pagamento do colaborador.
     * Só mexe em diária ativa que ainda não foi paga.
     *
     * Paga = status processado, ou diária dentro de lote processing/completed
     * (a carteira já foi creditada com o valor descontado).
     *
     * O lucro da diária não muda: o desconto saía do colaborador e entrava de
     * novo como custo de INSS. Ao devolver o valor e zerar o INSS, a margem fica igual.
     *
     * @return array{
     *     changed: list<array{id: int, collaborator: string, start: string, from: float, to: float, inss: float}>,
     *     skipped_paid: int,
     *     applied: bool
     * }
     */
    public function run(bool $apply): array
    {
        $changed = [];
        $skippedPaid = 0;

        $rates = DailyRate::query()
            ->with('collaborator:id,name')
            ->where('active', true)
            ->where('inss_paid', '>', 0)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'cancelado');
            })
            ->orderBy('id')
            ->get();

        foreach ($rates as $daily) {
            $inss = round((float) $daily->inss_paid, 2);
            if ($inss <= 0) {
                continue;
            }

            if ($this->alreadyPaid($daily)) {
                $skippedPaid++;
                continue;
            }

            $from = round((float) $daily->pay_amount, 2);
            $changed[] = [
                'id' => (int) $daily->id,
                'collaborator' => (string) ($daily->collaborator->name ?? 'Colaborador #'.$daily->collaborator_id),
                'start' => $daily->start?->format('d/m/Y') ?? '—',
                'from' => $from,
                'to' => round($from + $inss, 2),
                'inss' => $inss,
            ];
        }

        if ($apply && $changed !== []) {
            DB::transaction(function () use ($changed) {
                foreach ($changed as $row) {
                    DailyRate::query()->whereKey($row['id'])->update([
                        'pay_amount' => $row['to'],
                        'inss_paid' => 0,
                    ]);
                }
            });
        }

        return [
            'changed' => $changed,
            'skipped_paid' => $skippedPaid,
            'applied' => $apply,
        ];
    }

    private function alreadyPaid(DailyRate $daily): bool
    {
        if ($daily->status === 'processado') {
            return true;
        }

        if ($daily->company_id === null || $daily->start === null) {
            return false;
        }

        $day = $daily->start->toDateString();

        return FinancialBatches::query()
            ->where('company_id', $daily->company_id)
            ->whereIn('status', ['processing', 'completed'])
            ->whereDate('period_start', '<=', $day)
            ->whereDate('period_end', '>=', $day)
            ->exists();
    }
}
