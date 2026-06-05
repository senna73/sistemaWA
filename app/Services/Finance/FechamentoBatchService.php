<?php

namespace App\Services\Finance;

use App\Models\ConfigTable;
use App\Models\FinancialBatch;
use App\Models\DailyRate;
use App\Models\FinancialBatcheInvoices;
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
    
    /**
     * Liquida uma nota fiscal individualmente e injeta o valor no capital da empresa
     */
    public function liquidarNotaFiscal(int $invoiceId, int $batchId)
    {
        return DB::transaction(function () use ($invoiceId, $batchId) {
            
            $invoice = FinancialBatcheInvoices::where('id', $invoiceId)
                ->where('financial_batch_id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->received) {
                return false;
            }

            $invoice->update([
                'received' => true,
                'received_at' => now()
            ]);

            $this->ledgerService->receiveInvoicePayment(
                $invoice->amount, 
                $batchId, 
                $invoice->invoice_number
            );

            return true;
        });
    }
public function processarFechamento(int $batchId, float $valorSolicitadoCC)
    {
        try {
            $batch = FinancialBatches::with(['company.costCenter'])
                ->where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

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
                    
                $totalBrutoBatch = (float) $batch->total_amount;
                $somaEarnedDiarias = (float) $dailyRates->sum('earned');

                if (round($somaEarnedDiarias, 2) !== round($totalBrutoBatch, 2)) {
                    throw new \Exception("Inconsistência: Soma das diárias (R$ $somaEarnedDiarias) difere do total do lote (R$ $totalBrutoBatch).");
                }

                // Saídas operacionais básicas
                $somaPagarColaboradores = (float) $dailyRates->sum('pay_amount'); 
                
                $somaPagarCoordenadores = (float) $dailyRates->sum('coordinator_value');

                $taxConfig = ConfigTable::where('id', 'tax_default')->first();
                $taxRateRaw = $taxConfig ? (float) $taxConfig->value : 14.38; 
                $valorImpostos = $totalBrutoBatch * ($taxRateRaw / 100);

                $custoTotalOperacional = $valorImpostos + $somaPagarColaboradores + $somaPagarCoordenadores + $valorSolicitadoCC;
                
                if ($custoTotalOperacional > $totalBrutoBatch) {
                    $saldoDisponivel = $totalBrutoBatch - ($valorImpostos + $somaPagarColaboradores + $somaPagarCoordenadores);
                    throw new \Exception(
                        "Saldo Insuficiente para movimentar R$ $valorSolicitadoCC! " .
                        "Disponível após impostos, diárias e coordenação: R$ " . number_format($saldoDisponivel, 2)
                    );
                }

                // Execução dos Pagamentos dos Colaboradores (ColaboratorWallets)
                foreach ($dailyRates as $daily) {
                    $this->collaboratorWalletService->credit(
                        $daily->collaborator_id, 
                        $daily->pay_amount,
                        "Pagamento Lote #{$batch->id}",
                        [],
                        $daily->start
                    );
                }

                $pagamentosCoordenadores = $dailyRates->whereNotNull('coordinator_id')
                    ->where('coordinator_value', '>', 0)
                    ->groupBy('coordinator_id');

                foreach ($pagamentosCoordenadores as $coordinatorId => $diariasDoCoordenador) {
                    $totalCustoCoordenador = (float) $diariasDoCoordenador->sum('coordinator_value');
                    
                    $this->collaboratorWalletService->credit(
                        $coordinatorId,
                        $totalCustoCoordenador,
                        "Remuneração de Coordenação Lote #{$batch->id}",
                        [],
                        now()
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
                    'collaborator_total' => $somaPagarColaboradores,
                    'coordinator_total' => $somaPagarCoordenadores
                ]);

                return $batch;
            });

        } catch (\Exception $e) {
            Log::error("FALHA FECHAMENTO LOTE #{$batchId}: " . $e->getMessage());
            throw $e; 
        }
    }
}