<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\AccountingListCheck;
use App\Models\AccountingListRow;
use App\Services\Rh\AccountingListService;
use App\Support\PopCatalog;
use App\Support\RhActivitySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class AccountingListController extends Controller
{
    public function show(AccountingListService $lists): View
    {
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::ACCOUNTING, request()->user());
        $check = $lists->current();
        $pending = $check
            ? $check->rows()->whereNull('resolved_at')->where('bucket', '!=', AccountingListRow::BUCKET_OK)->orderBy('id')->get()
            : collect();
        $ok = $check
            ? $check->rows()->where(function ($query) {
                $query->where('bucket', AccountingListRow::BUCKET_OK)
                    ->orWhereNotNull('resolved_at');
            })->count()
            : 0;
        $current = $pending->first();
        if (request()->filled('row')) {
            $current = $pending->firstWhere('id', request()->integer('row')) ?? $current;
        }

        return view('work.accounting', [
            'check' => $check,
            'pending' => $pending,
            'ok' => $ok,
            'current' => $current,
            'candidates' => $current
                ? \App\Models\Collaborator::query()->whereIn('id', $current->candidate_ids ?? [])->get()
                : collect(),
        ]);
    }

    public function store(Request $request, AccountingListService $lists): RedirectResponse
    {
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::ACCOUNTING, $request->user());
        $request->validate([
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        try {
            $lists->ingest($request->user(), $request->file('attachment'));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'attachment' => $e->getMessage(),
            ]);
        }

        return redirect()->route('work.accounting')->with('status', 'Lista importada. Nada foi alterado. Confira os desvios um a um.');
    }

    public function apply(Request $request, AccountingListRow $row, AccountingListService $lists): RedirectResponse
    {
        abort_unless($request->user()?->can(PopCatalog::PERMISSION_ACCOUNTING_LIST), 403);
        RhActivitySettings::abortUnlessVisible(RhActivitySettings::ACCOUNTING, $request->user());

        $lists->apply($row, $request->user(), $request->all());

        return redirect()->route('work.accounting')->with('status', 'Item conferido conforme a lista da contabilidade.');
    }
}
