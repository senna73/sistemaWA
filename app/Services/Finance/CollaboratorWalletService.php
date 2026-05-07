<?php

namespace App\Services\Finance;

use App\Models\Collaborator;
use App\Models\CollaboratorWallet;
use App\Models\CollaboratorWalletTransactions;
use App\Models\DailyRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Carbon;

class CollaboratorWalletService
{
    /**
     * Adiciona saldo à carteira do colaborador
     */
    public function credit(int $collaboratorId, float $amount, string $description, array $metadata = [], ?string $occurredAt = null)
    {
        return $this->executeTransaction($collaboratorId, abs($amount), 'credit', $description, $metadata, $occurredAt);
    }
    /**
     * Remove saldo da carteira do colaborador
     */
    public function debit(int $collaboratorId, float $amount, string $description, array $metadata = [], ?string $occurredAt = null)
    {
        return $this->executeTransaction($collaboratorId, abs($amount), 'debit', $description, $metadata, $occurredAt);
    }

    /**
     * Função de transação modularizada
     */
    private function executeTransaction(int $collaboratorId, float $amount, string $type, string $description, array $metadata, ?string $occurredAt = null)    {
        return DB::transaction(function () use ($collaboratorId, $amount, $type, $description, $metadata, $occurredAt) {
            $wallet = CollaboratorWallet::firstOrCreate(
                        ['collaborator_id' => $collaboratorId],
                        [
                            'balance' => 0, 
                            'total_added' => 0, 
                            'total_spent' => 0
                        ]
                );

            $wallet = CollaboratorWallet::where('id', $wallet->id)
                        ->lockForUpdate()
                        ->first();

            $balanceBefore = (float) $wallet->balance;

            if ($type === 'debit' && $balanceBefore < $amount) {
                throw new Exception("Saldo insuficiente. Saldo atual: R$ " . number_format($balanceBefore, 2, ',', '.'));
            }

            $balanceAfter = ($type === 'credit') 
                ? $balanceBefore + $amount 
                : $balanceBefore - $amount;

            $wallet->update([
                'balance'     => $balanceAfter,
                'total_added' => ($type === 'credit') ? $wallet->total_added + $amount : $wallet->total_added,
                'total_spent' => ($type === 'debit')  ? $wallet->total_spent + $amount : $wallet->total_spent,
            ]);
            $date = $occurredAt ? Carbon::parse($occurredAt) : now();
            
            return CollaboratorWalletTransactions::create([
                'reference_id'           => (string) Str::uuid(),
                'collaborator_wallet_id' => $wallet->id,
                'amount'                 => $amount,
                'balance_before'         => $balanceBefore,
                'balance_after'          => $balanceAfter,
                'type'                   => $type,
                'description'            => $description,
                'metadata'               => $metadata,
                'occurred_at'            => $date,
            ]);
        });
    }

    public function exportPdf(Request $request)
    {
        $months = (int) $request->get('months', 1);
        $start = now()->subMonths($months)->startOfDay();
        $end = now()->endOfDay();

        $workedIds = DailyRate::whereBetween('start', [$start, $end])
            ->distinct()
            ->pluck('collaborator_id');

        $nonWorkingCollaborators = Collaborator::whereNotIn('id', $workedIds)
            ->where('active', true)
            ->with(['dailyRates' => fn($q) => $q->latest('start')])
            ->get();

        $data = [
            'title' => 'Relatório de Inatividade',
            'period' => $months . ($months > 1 ? ' Meses' : ' Mês'),
            'date' => now()->format('d/m/Y H:i'),
            'collaborators' => $nonWorkingCollaborators
        ];

        $pdf = \PDF::loadView('app.finance.analytics.pdf_report', $data);

        return $pdf->download("relatorio_inatividade_{$months}_meses.pdf");
    }
}
