<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Company;
use App\Models\DailyRateReleaseRequest;
use App\Services\Rh\DailyRateReleaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailyRateReleaseController extends Controller
{
    public function index(DailyRateReleaseService $releases): View
    {
        $items = DailyRateReleaseRequest::query()
            ->with(['collaborator', 'company', 'requester', 'reviewer'])
            ->when(
                request()->user()?->coordinatorWorkbench(),
                fn ($query) => $query->where('requested_by', request()->user()->id)
            )
            ->latest('id')
            ->limit(100)
            ->get();

        return view('work.releases.index', [
            'items' => $items,
            'canApprove' => $releases->canApprove(request()->user()),
        ]);
    }

    public function create(Request $request): View
    {
        $collaborator = $request->filled('collaborator_id')
            ? Collaborator::query()->find($request->integer('collaborator_id'))
            : null;

        return view('work.releases.create', [
            'collaborator' => $collaborator,
            'companies' => Company::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, DailyRateReleaseService $releases): RedirectResponse
    {
        $validated = $request->validate([
            'collaborator_id' => ['required', 'exists:collaborators,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'daily_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $releases->request(
            $request->user(),
            Collaborator::query()->findOrFail($validated['collaborator_id']),
            $validated
        );

        if ($request->user()?->can(\App\Support\AccessControl::PERMISSION_WORK) || $request->user()?->isSuperAdmin()) {
            return redirect()->route('work.releases.index')->with('status', 'Pedido de liberação enviado ao RH/Direção.');
        }

        return back()->with('status', 'Pedido de liberação enviado ao RH/Direção.');
    }

    public function decide(Request $request, DailyRateReleaseRequest $release, DailyRateReleaseService $releases): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,refuse'],
            'notes' => ['nullable', 'string'],
        ]);
        $releases->decide($release, $request->user(), $validated['decision'], $validated['notes'] ?? null);

        return back()->with('status', $validated['decision'] === 'approve' ? 'Liberação aprovada.' : 'Liberação recusada.');
    }
}
