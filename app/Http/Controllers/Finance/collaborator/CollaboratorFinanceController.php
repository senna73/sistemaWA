<?php

namespace App\Http\Controllers\Finance\collaborator;

use App\Http\Controllers\Controller;

class CollaboratorFinanceController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return redirect()->route('portal.earnings');
    }

    public function get_wallet(int $id)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return redirect()->route('portal.earnings', ['collaborator_id' => $id]);
    }
}
