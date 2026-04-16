<?php

namespace App\Services\Finance;

use App\Models\Ledger;
use App\Models\CashAccount;
use App\Models\CollaboratorWallet;
use App\Models\FinancialBatches;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    // Registra a entrada de capital via pagamento de boleto de um lote.
    public function receiveBatchPayment(int $batchId, int $cashAccountId = 1): Ledger
    {
        $batch = FinancialBatches::findOrFail($batchId);
        
        return $this->execute(
            amount: $batch->total_amount,
            type: 'credit',
            category: 'INVOICE_RECEIPT',
            description: "Recebimento de Boleto - Lote #{$batchId}",
            cashAccountId: $cashAccountId,
            data: ['financial_batch_id' => $batchId]
        );
    }
    // Registra a saída de capital para quando há pagamento ao colaborador.
    public function payCollaborator(int $collaboratorId, float $amount, string $description, int $cashAccountId = 1): Ledger
    {
        // Busca a carteira para garantir que o vínculo no Ledger esteja correto
        $wallet = CollaboratorWallet::where('collaborator_id', $collaboratorId)->firstOrFail();

        return $this->execute(
            amount: $amount,
            type: 'debit',
            category: 'COLLABORATOR_PAYMENT',
            description: $description,
            cashAccountId: $cashAccountId,
            data: ['collaborator_wallet_id' => $wallet->id]
        );
    }

    // Pagamento de Custos/Despesas a partir do Centro de Custo.
    public function payCost(int $costCenterId, float $amount, string $description, int $cashAccountId = 1): Ledger
    {
        return $this->execute(
            amount: $amount,
            type: 'debit',
            category: 'EXPENSE_PAYMENT',
            description: $description,
            cashAccountId: $cashAccountId,
            data: ['cost_center_id' => $costCenterId]
        );
    }

    // Centralizado para gerenciar CashAccounts ( Caixas da Empresa).
    private function execute(
        float $amount, 
        string $type, 
        string $category, 
        string $description, 
        int $cashAccountId, 
        array $data = []
    ): Ledger {
        return DB::transaction(function () use ($amount, $type, $category, $description, $cashAccountId, $data) {
            
            $cashAccount = CashAccount::lockForUpdate()->findOrFail($cashAccountId);
            
            $amount = abs($amount);
            $oldBalance = $cashAccount->balance;

            if ($type === 'credit') {
                $newBalance = $oldBalance + $amount;
                $cashAccount->increment('total_added', $amount);
            } else {
                $newBalance = $oldBalance - $amount;
                $cashAccount->increment('total_spent', $amount);
            }

            $cashAccount->update(['balance' => $newBalance]);

            return Ledger::create([
                'cash_account_id'        => $cashAccountId,
                'financial_batch_id'     => $data['financial_batch_id'] ?? null,
                'user_id'                => $data['user_id'] ?? null,
                'collaborator_wallet_id' => $data['collaborator_wallet_id'] ?? null,
                'cost_center_id'         => $data['cost_center_id'] ?? null,
                'amount'                 => $amount,
                'balance_after'          => $newBalance,
                'entry_type'             => $type,
                'category'               => $category,
                'description'            => $description,
                'metadata'               => $data['metadata'] ?? null,
            ]);
        });
    }
}