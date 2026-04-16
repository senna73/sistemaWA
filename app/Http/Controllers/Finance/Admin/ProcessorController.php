<?php

namespace App\Http\Controllers\Finance\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collaborator; // Ajuste conforme seu modelo
use App\Models\CollaboratorWallet;
use App\Models\Cost;
use App\Models\FinancialCost; // Ajuste conforme seu modelo
use App\Services\Finance\CollaboratorWalletService;
use App\Services\Finance\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessorController extends Controller
{
    /**
     * Tela Principal do Processador Financeiro
     */
    public function index()
    {
        $countCollaborators = Collaborator::whereHas('wallet', function($q) {
            $q->where('balance', '>', 0);
        })->count();

        $countPixPending = Cost::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->count();

        return view('app.finance.admin.processor.index', compact('countCollaborators', 'countPixPending'));
    }

    /**
     * Tela de Pagamentos de Colaboradores
     */
    public function collaboratorPayments()
    {
        $wallets = CollaboratorWallet::with('collaborator')
            ->where('balance', '>', 0)
            ->get();

        return view('app.finance.admin.processor.collaborator-payments', compact('wallets'));
    }

    /**
     * Tela de Custos PIX
     */
    public function pixCosts()
    {
        $pendingCosts = Cost::where('status', 'pending')
            ->where('payment_method', 'pix')
            ->orderBy('date', 'asc')
            ->get();
        
        return view('app.finance.admin.processor.pix-costs', compact('pendingCosts'));
    }

    public function payWallet(
        Collaborator $collaborator,
        CollaboratorWalletService $walletService, 
        LedgerService $ledgerService 
    ) {
        $wallet = $collaborator->wallet;

        if (!$wallet || $wallet->balance <= 0) {
            return redirect()->back()->with('error', 'Esta carteira não possui saldo para liquidação.');
        }

        $amountToPay = (float) $wallet->balance;

        try {
            DB::transaction(function () use ($collaborator, $amountToPay, $walletService, $ledgerService) {
                
                $walletService->debit(
                    $collaborator->id,
                    $amountToPay,
                    "Liquidação de saldo: Pagamento realizado via painel administrativo"
                );

                $ledgerService->payCollaborator(
                    collaboratorId: $collaborator->id,
                    amount: $amountToPay,
                    description: "Liquidação de Saldo - Colaborador: {$collaborator->name}",
                    cashAccountId: 1
                );
            });

            return redirect()->back()->with('success', "Pagamento de R$ " . number_format($amountToPay, 2, ',', '.') . " processado e registrado no ledger!");

        } catch (\Exception $e) {
            Log::error("Erro na liquidação da carteira do colaborador {$collaborator->id}: " . $e->getMessage());
            return redirect()->back()->with('error', 'Falha ao processar: ' . $e->getMessage());
        }
    }

    public function rejectPix(Cost $cost)
    {
        try {
            $cost->update([
                'value'   => 0,
                'status'  => 'completed',
                'paid_at' => null,
            ]);

            return redirect()->back()->with('success', 'Solicitação de PIX rejeitada e zerada.');

        } catch (\Exception $e) {
            Log::error("Erro ao rejeitar custo PIX ID {$cost->id}: " . $e->getMessage());
            return redirect()->back()->with('error', 'Falha ao rejeitar o item.');
        }
    }

    public function payPix(Cost $cost, LedgerService $ledgerService)
    {
        try {
            DB::transaction(function () use ($cost, $ledgerService) {
                $cost->update([
                    'status'  => 'completed',
                    'paid_at' => now(),
                ]);

                $ledgerService->payCost(
                    costCenterId: $cost->cost_center_id ?? 1,
                    amount: $cost->value,
                    description: "Pagamento PIX: {$cost->description}",
                    cashAccountId: 1
                );
            });

            return redirect()->back()->with('success', 'Pagamento PIX realizado e registrado no ledger!');

        } catch (\Exception $e) {
            Log::error("Erro ao liquidar custo PIX {$cost->id}: " . $e->getMessage());
            return redirect()->back()->with('error', 'Falha ao processar pagamento: ' . $e->getMessage());
        }
    }
}