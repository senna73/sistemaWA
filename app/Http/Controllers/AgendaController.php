<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\User;
use App\Services\Rh\AgendaService;
use App\Support\PopCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(AgendaService $agenda, Request $request): View
    {
        $user = $request->user();
        $board = $agenda->board($user);
        $personId = $request->integer('assignee_id') ?: null;
        $period = $request->string('period')->toString() ?: 'today';
        [$from, $to] = match ($period) {
            'week' => [now()->startOfDay(), now()->copy()->addDays(7)->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
        $person = $personId ? User::query()->find($personId) : ($user?->isCoordinator() ? $user : null);
        $indicators = $agenda->indicators($person, $from, $to);

        return view('agenda.index', [
            'board' => $board,
            'indicators' => $indicators,
            'period' => $period,
            'personId' => $personId,
            'types' => PopCatalog::agendaTypesFor($user),
            'demandCategories' => PopCatalog::demandCategoriesFor($user),
            'utilities' => PopCatalog::agendaUtilities(),
            'recurrences' => PopCatalog::agendaRecurrences(),
            'statuses' => PopCatalog::agendaStatuses(),
            'assignees' => User::query()
                ->whereIn('role', PopCatalog::agendaAssigneeRoles())
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'collaborators' => \App\Models\Collaborator::query()->where('active', true)->orderBy('name')->limit(300)->get(['id', 'name']),
            'companies' => \App\Models\Company::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, AgendaService $agenda): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['required', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['nullable', 'string', 'max:20'],
            'type' => ['required', 'in:'.implode(',', array_keys(PopCatalog::agendaTypesFor($request->user())))],
            'area' => ['nullable', 'string', 'max:255'],
            'recurrence' => ['nullable', 'in:'.implode(',', array_keys(PopCatalog::agendaRecurrences()))],
            'collaborator_id' => ['nullable', 'exists:collaborators,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(PopCatalog::demandCategories()))],
        ]);

        $agenda->create($request->user(), $validated);

        return back()->with('status', 'Atividade criada.');
    }

    public function status(Request $request, AgendaItem $item, AgendaService $agenda): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(PopCatalog::agendaStatuses()))],
        ]);
        $agenda->updateStatus($item, $request->user(), $validated['status']);

        return back()->with('status', 'Status atualizado.');
    }
}
