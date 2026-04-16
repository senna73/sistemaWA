<?php

namespace App\Http\Controllers\Finance\collaborator;
use App\Http\Controllers\Controller;
use App\Models\DailyRate;
use App\Models\CollaboratorWallet;
use App\Models\CollaboratorWalletTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class CollaboratorFinanceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $wallet = CollaboratorWallet::firstOrCreate(
            ['collaborator_id' => $user->id],
            ['balance' => 0, 'total_added' => 0, 'total_spent' => 0]
        );

        $history = DailyRate::with('company')
            ->where('collaborator_id', $user->id)
            ->orderBy('start', 'desc')
            ->paginate(15);

        $transactions = CollaboratorWalletTransactions::where('collaborator_wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        $pendingAmount = DailyRate::where('collaborator_id', $user->id)
            ->where('status', 'pending')
            ->sum('pay_amount');

        return view('app.finance.admin.collaborator.earnings', [
            'wallet'        => $wallet,
            'history'       => $history,
            'transactions'  => $transactions,
            'pendingAmount' => $pendingAmount
        ]);
    }

    public function get_wallet(int $id)
    {
        $wallet = CollaboratorWallet::firstOrCreate(
            ['collaborator_id' => $id],
            ['balance' => 0, 'total_added' => 0, 'total_spent' => 0]
        );

        $transactions = CollaboratorWalletTransactions::where('collaborator_wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        $pendingAmount = DailyRate::where('collaborator_id', $id)
            ->where('status', 'pending')
            ->sum('pay_amount');

        return view('app.finance.admin.collaborator.earnings', [
            'wallet'        => $wallet,
            'transactions'  => $transactions,
            'pendingAmount' => $pendingAmount
        ]);
    }
}