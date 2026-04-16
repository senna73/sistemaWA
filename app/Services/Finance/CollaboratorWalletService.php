<?php

namespace App\Services\Finance;

use App\Models\CollaboratorWallet;
use App\Models\CollaboratorWalletTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class CollaboratorWalletService
{
    /**
     * Adiciona saldo à carteira do colaborador
     */
    public function credit(int $collaboratorId, float $amount, string $description, array $metadata = [])
    {
        return $this->executeTransaction($collaboratorId, abs($amount), 'credit', $description, $metadata);
    }

    /**
     * Remove saldo da carteira do colaborador
     */
    public function debit(int $collaboratorId, float $amount, string $description, array $metadata = [])
    {
        return $this->executeTransaction($collaboratorId, abs($amount), 'debit', $description, $metadata);
    }

    /**
     * Função de transação modularizada
     */
    private function executeTransaction(int $collaboratorId, float $amount, string $type, string $description, array $metadata)
    {
        return DB::transaction(function () use ($collaboratorId, $amount, $type, $description, $metadata) {
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

            return CollaboratorWalletTransactions::create([
                'reference_id'           => (string) Str::uuid(),
                'collaborator_wallet_id' => $wallet->id,
                'amount'                 => $amount,
                'balance_before'         => $balanceBefore,
                'balance_after'          => $balanceAfter,
                'type'                   => $type,
                'description'            => $description,
                'metadata'               => $metadata,
            ]);
        });
    }
}
