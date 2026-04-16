<?php

namespace app\Http\Controllers\Finance\Admin;


class LedgerController
{
    public function index()
    {
        $accounts = \App\Models\CashAccount::all();
        
        $entries = \App\Models\Ledger::with(['user', 'collaboratorWallet.collaborator', 'costCenter'])
            ->latest()
            ->paginate(15);
    
        return view('app.finance.admin.ledger.index', compact('accounts', 'entries'));
    }
}
