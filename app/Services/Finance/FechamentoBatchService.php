<?php

namespace App\Services\Finance;

use App\Models\ConfigTable;
use App\Models\FinancialBatch;
use App\Models\DailyRate;
use App\Models\FinancialBatches;
use App\Models\LeaderCostCenter;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FechamentoBatchService
{
    
    public function __construct(
        protected LedgerService $ledgerService,
        protected CollaboratorWalletService $collaboratorWalletService
    ) 
    {
        
    }
    public function processarRecebimento(int $batchId)
    {
        return DB::transaction(function () use ($batchId) {
            $batch = FinancialBatches::where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($batch->status === 'completed') {
                throw new \Exception("Este lote já foi marcado como recebido anteriormente.");
            }

            $this->ledgerService->receiveBatchPayment($batchId);

            $batch->update([
                'status' => 'completed',
                'received_at' => now(),
            ]);

            return $batch;
        });
    }

    public function processarFechamento(int $batchId, float $valorSolicitadoCC)
    {
        try {
            $batch = FinancialBatches::with(['company.costCenter'])
                ->where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            $start = Carbon::parse($batch->period_start)->startOfDay(); // 00:00:00
            $end = Carbon::parse($batch->period_end)->endOfDay();       // 23:59:59

            $dailyRates = DailyRate::where('company_id', $batch->company_id)
                ->whereBetween('start', [$start, $end])
                ->where('active', true)
                ->get();
            if ($batch->status !== 'pending') {
                throw new \Exception("Lote #{$batchId} já foi processado.");
            }

            return DB::transaction(function () use ($batch, $valorSolicitadoCC) {
                
                // Recupera as diárias do período
                $dailyRates = DailyRate::where('company_id', $batch->company_id)
                    ->whereDate('start', '>=', $batch->period_start)
                    ->whereDate('start', '<=', $batch->period_end)
                    ->where('active', true)
                    ->get();
                    
                // O Bruto (total_amount) deve ser a soma do 'earned' das diárias
                $totalBrutoBatch = (float) $batch->total_amount;
                $somaEarnedDiarias = (float) $dailyRates->sum('earned');

                if (round($somaEarnedDiarias, 2) !== round($totalBrutoBatch, 2)) {
                    throw new \Exception("Inconsistência: Soma das diárias (R$ $somaEarnedDiarias) difere do total do lote (R$ $totalBrutoBatch).");
                }

                // Saída
                $somaPagarColaboradores = (float) $dailyRates->sum('pay_amount'); 

                $taxConfig = ConfigTable::where('id', 'tax_default')->first();
                $taxRateRaw = $taxConfig ? (float) $taxConfig->value : 14.38; 
                $valorImpostos = $totalBrutoBatch * ($taxRateRaw / 100);

                // O Bruto deve cobrir: Impostos + Pagamentos + Valor solicitado para o Centro de Custo
                $custoTotalOperacional = $valorImpostos + $somaPagarColaboradores + $valorSolicitadoCC;
                if ($custoTotalOperacional > $totalBrutoBatch) {
                    $saldoDisponivel = $totalBrutoBatch - ($valorImpostos + $somaPagarColaboradores);
                    throw new \Exception(
                        "Saldo Insuficiente para movimentar R$ $valorSolicitadoCC! " .
                        "Disponível após impostos e diárias: R$ " . number_format($saldoDisponivel, 2)
                    );
                }

                // Execução dos Pagamentos (ColaboratorWallets)
                foreach ($dailyRates as $daily) {
                    $this->collaboratorWalletService->credit(
                        $daily->collaborator_id, 
                        $daily->pay_amount,
                        "Pagamento Lote #{$batch->id}"
                    );
                }

                // Movimentação do Centro de Custo
                $costCenter = LeaderCostCenter::firstOrCreate(
                    ['company_id' => $batch->company_id],
                    [
                        'balance' => 0, 
                        'name' => "CC " . $batch->company->name,
                        'leader_id' => $batch->company->leader_id
                    ]
                );
                $costCenter->increment('balance', $valorSolicitadoCC);

                $batch->update([
                    'status'             => 'processing',
                    'processed_at'       => now(),
                    'tax_amount'         => $valorImpostos,
                    'cost_center_amount' => $valorSolicitadoCC,
                    'collaborator_total' => $somaPagarColaboradores
                ]);

                return $batch;
            });

        } catch (\Exception $e) {
            Log::error("FALHA FECHAMENTO LOTE #{$batchId}: " . $e->getMessage());
            throw $e; 
        }
    }
}