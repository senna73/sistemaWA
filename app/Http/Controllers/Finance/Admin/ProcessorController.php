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
    public function collaboratorPayments(Request $request)
    {
        $today = now();

        if ($today->day <= 15) {
            $start = $today->copy()->subMonth()->day(16)->startOfDay();
            $end = $today->copy()->subMonth()->endOfMonth();
        } else {
            $start = $today->copy()->startOfMonth();
            $end = $today->copy()->day(15)->endOfDay();
        }

        $wallets = CollaboratorWallet::with('collaborator:id,name,pix_key')
            ->withSum(['transactions as period_credits_sum' => function ($query) use ($start, $end) {
                $query->whereBetween('occurred_at', [$start, $end])
                    ->where('type', 'credit');
            }], 'amount')
            ->with(['transactions' => function ($query) use ($start) {
                $query->where('occurred_at', '>=', $start)
                    ->where('type', 'debit');
            }])
            ->get();

        $wallets = $wallets->filter(function ($wallet) {
            $creditos = (float) $wallet->period_credits_sum;

            if ($creditos <= 0) {
                return false;
            }

            // Verifica se existe um débito que "anulou" esses créditos
            $jaPago = $wallet->transactions->contains(function ($transaction) use ($creditos) {
                return abs((float) $transaction->amount) == $creditos;
            });

            return !$jaPago;
        });

        return view('app.finance.admin.processor.collaborator-payments', compact('wallets', 'start', 'end'));
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
        Request $request,
        $walletId,
        CollaboratorWalletService $walletService, 
        LedgerService $ledgerService 
    ) {
        $wallet = CollaboratorWallet::with('collaborator')->findOrFail($walletId);
        $collaborator = $wallet->collaborator;

        $amountFromRequest = (float) $request->input('amount');
        
        if ($amountFromRequest <= 0 || $wallet->balance < $amountFromRequest) {
            return redirect()->back()->with('error', 'Valor inválido ou saldo insuficiente.');
        }

        $now = now();
        if ($now->day > 15) {
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->day(15)->endOfDay();
        } else {
            $lastMonth = $now->copy()->subMonth();
            $start = $lastMonth->copy()->day(16)->startOfDay();
            $end = $lastMonth->copy()->endOfMonth();
        }
        $periodoFormatado = "de " . $start->format('d/m') . " a " . $end->format('d/m');

        try {
            DB::transaction(function () use ($collaborator, $amountFromRequest, $walletService, $ledgerService, $periodoFormatado) {
                
                $walletService->debit(
                    $collaborator->id,
                    $amountFromRequest,
                    "Pagamento pelos serviços prestados - Período {$periodoFormatado}"
                );

                $ledgerService->payCollaborator(
                    collaboratorId: $collaborator->id,
                    amount: $amountFromRequest,
                    description: "Pagamento Quinzenal ({$periodoFormatado}) - Colaborador: {$collaborator->name}",
                    cashAccountId: 1 
                );
            });

            return redirect()->back()->with('success', "Pagamento de R$ " . number_format($amountFromRequest, 2, ',', '.') . " realizado!");

        } catch (\Exception $e) {
            Log::error("Erro ao liquidar: " . $e->getMessage());
            return redirect()->back()->with('error', 'Erro interno ao processar pagamento: ' . $e->getMessage());
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