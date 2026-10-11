<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Models\AgendaSchedule;
use App\Models\User;
use App\Services\Rh\AgendaService;
use App\Support\PopCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(AgendaService $agenda, Request $request): View|Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $context = $this->periodContext($request, 'director');
        $shared = $this->catalog($request, $context);
        $shared['schedules'] = AgendaSchedule::query()->orderBy('title')->get();
        $shared['planned'] = $agenda->plannedBySector($context['from'], $context['to'], $context['workFunction']);
        $shared['screen'] = 'agenda';
        $shared['filterRoute'] = 'agenda.index';

        return $this->agendaView($shared, $request, 'agenda.partials.agenda-panel', 'agenda.index');
    }

    public function board(AgendaService $agenda, Request $request): View|Response
    {
        $user = $request->user();
        $mode = $this->modeFor($user);
        abort_unless(in_array($mode, ['director', 'executor'], true), 403);

        $context = $this->periodContext($request, $mode);
        $personId = $request->integer('assignee_id') ?: null;
        $shared = $this->catalog($request, $context);
        $shared['personId'] = $personId;
        $shared['indicators'] = $agenda->directorIndicators($context['from'], $context['to'], $context['workFunction'], $personId);
        $shared['functionBoard'] = $agenda->functionBoard($context['from'], $context['to'], $context['workFunction'], $personId);
        $shared['functionMembers'] = User::query()
            ->where('active', true)
            ->whereNotNull('work_function')
            ->when($context['workFunction'], fn ($query) => $query->where('work_function', $context['workFunction']))
            ->orderBy('name')
            ->get(['id', 'name', 'work_function']);
        $shared['productivity'] = $mode === 'director'
            ? $agenda->productivity($context['from'], $context['to'], $context['workFunction'], $personId)
            : collect();
        $shared['screen'] = 'board';
        $shared['filterRoute'] = 'agenda.board';

        return $this->agendaView($shared, $request, 'agenda.partials.board-panel', 'agenda.board');
    }

    public function store(Request $request, AgendaService $agenda): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->can(PopCatalog::PERMISSION_AGENDA), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'work_function' => ['required', Rule::in(array_keys(PopCatalog::workFunctions()))],
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

        return $this->saved($request, 'Atividade criada.');
    }

    public function storeSchedule(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        AgendaSchedule::query()->create([
            ...$this->schedulePayload($request),
            'created_by' => $request->user()->id,
        ]);

        return $this->saved($request, 'Agenda criada. As atividades entram sozinhas quando o prazo se aproxima.');
    }

    public function updateSchedule(Request $request, AgendaSchedule $schedule): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $schedule->update($this->schedulePayload($request));

        return $this->saved($request, 'Agenda atualizada.');
    }

    public function status(Request $request, AgendaItem $item, AgendaService $agenda): RedirectResponse|JsonResponse
    {
        $this->assertCanUpdateItem($request->user(), $item);

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(PopCatalog::agendaStatuses()))],
        ]);
        $item = $agenda->updateStatus($item, $request->user(), $validated['status']);

        return $this->saved($request, 'Status atualizado.', $item);
    }

    public function assign(Request $request, AgendaItem $item, AgendaService $agenda): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $validated = $request->validate([
            'assignee_id' => ['nullable', 'exists:users,id'],
        ]);
        $item = $agenda->assign($item, $request->user(), $validated['assignee_id'] ?? null);

        return $this->saved($request, 'Responsável atualizado.', $item);
    }

    public function correction(Request $request, AgendaItem $item, AgendaService $agenda): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $item = $agenda->requestCorrection($item, $request->user(), $validated['note']);

        return $this->saved($request, 'Correção pedida.', $item);
    }

    private function agendaView(array $shared, Request $request, string $fragment, string $page): View|Response
    {
        if ($request->headers->get('X-Agenda-Fragment') === '1') {
            return response()->view($fragment, $shared);
        }

        return view($page, $shared);
    }

    /**
     * @return array{mode: string, period: string, workFunction: ?string, anchor: Carbon, from: Carbon, to: Carbon}
     */
    private function periodContext(Request $request, string $mode): array
    {
        $period = $request->string('period')->toString() ?: 'month';
        if (! in_array($period, ['month', 'week', 'day'], true)) {
            $period = 'month';
        }
        $anchor = $this->anchor($request);
        [$from, $to] = $this->bounds($period, $anchor);

        return [
            'mode' => $mode,
            'period' => $period,
            'workFunction' => $this->requestedFunction($request, $request->user(), $mode),
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param  array{mode: string, period: string, workFunction: ?string, anchor: Carbon, from: Carbon, to: Carbon}  $context
     * @return array<string, mixed>
     */
    private function catalog(Request $request, array $context): array
    {
        $user = $request->user();
        $anchor = $context['anchor'];
        $period = $context['period'];

        return [
            'mode' => $context['mode'],
            'period' => $period,
            'personId' => null,
            'types' => PopCatalog::agendaTypesFor($user),
            'demandCategories' => PopCatalog::demandCategoriesFor($user),
            'utilities' => PopCatalog::agendaUtilities(),
            'recurrences' => PopCatalog::agendaRecurrences(),
            'statuses' => PopCatalog::agendaStatuses(),
            'assignees' => $this->assignees(),
            'collaborators' => \App\Models\Collaborator::query()->where('active', true)->orderBy('name')->limit(300)->get(['id', 'name']),
            'companies' => \App\Models\Company::query()->orderBy('name')->get(['id', 'name']),
            'workFunctions' => PopCatalog::workFunctions(),
            'scheduleRecurrences' => PopCatalog::scheduleRecurrences(),
            'weekdays' => PopCatalog::weekdays(),
            'priorities' => PopCatalog::agendaPriorities(),
            'workFunction' => $context['workFunction'],
            'anchor' => $anchor->toDateString(),
            'periodLabel' => $this->periodLabel($period, $anchor, $context['from'], $context['to']),
            'previous' => $this->shift($period, $anchor, -1)->toDateString(),
            'next' => $this->shift($period, $anchor, 1)->toDateString(),
            'functionMembers' => User::query()
                ->where('active', true)
                ->whereNotNull('work_function')
                ->orderBy('name')
                ->get(['id', 'name', 'work_function']),
        ];
    }

    private function saved(Request $request, string $message, ?AgendaItem $item = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'item' => $item ? $this->itemState($item->loadMissing('assignee')) : null,
                'refresh' => $item === null,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemState(AgendaItem $item): array
    {
        return [
            'id' => $item->id,
            'status' => $item->status,
            'status_label' => $item->statusLabel(),
            'assignee_id' => $item->assignee_id,
            'assignee_name' => $item->assignee?->name,
            'correction_note' => $item->correction_note,
            'late' => $item->effectiveStatus() === AgendaItem::STATUS_LATE,
            'column' => match ($item->status) {
                AgendaItem::STATUS_IN_PROGRESS => 'in_progress',
                AgendaItem::STATUS_IN_REVIEW => 'in_review',
                AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED => null,
                default => 'pending',
            },
            'remove' => in_array($item->status, [AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED], true),
        ];
    }

    private function modeFor(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return 'director';
        }

        if (filled($user->work_function)) {
            return 'executor';
        }

        return 'classic';
    }

    private function requestedFunction(Request $request, User $user, string $mode): ?string
    {
        if ($mode === 'executor') {
            return $user->work_function;
        }

        if ($mode !== 'director') {
            return null;
        }

        $function = $request->string('work_function')->toString();
        if ($function === '' || ! isset(PopCatalog::workFunctions()[$function])) {
            return null;
        }

        return $function;
    }

    private function anchor(Request $request): Carbon
    {
        if (! $request->filled('on')) {
            return now();
        }

        try {
            return Carbon::parse($request->input('on'));
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bounds(string $period, Carbon $anchor): array
    {
        return match ($period) {
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
        };
    }

    private function shift(string $period, Carbon $anchor, int $direction): Carbon
    {
        return match ($period) {
            'week' => $anchor->copy()->addWeeks($direction),
            'day' => $anchor->copy()->addDays($direction),
            default => $anchor->copy()->addMonths($direction),
        };
    }

    private function periodLabel(string $period, Carbon $anchor, Carbon $from, Carbon $to): string
    {
        $label = $anchor->copy()->locale('pt_BR');

        return match ($period) {
            'week' => $from->format('d/m').' – '.$to->format('d/m/Y'),
            'day' => $label->isoFormat('DD/MM/YYYY'),
            default => ucfirst($label->isoFormat('MMMM YYYY')),
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function assignees()
    {
        return User::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $assignee) => PopCatalog::canReceiveAgenda($assignee))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulePayload(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'work_function' => ['required', Rule::in(array_keys(PopCatalog::workFunctions()))],
            'recurrence' => ['required', Rule::in(array_keys(PopCatalog::scheduleRecurrences()))],
            'anchor_weekday' => ['nullable', 'integer', 'between:1,7', 'required_if:recurrence,weekly'],
            'anchor_day' => ['nullable', 'integer', 'between:1,31', 'required_if:recurrence,monthly', 'required_if:recurrence,bimonthly'],
            'due_time' => ['required', 'date_format:H:i'],
            'priority' => ['required', Rule::in(array_keys(PopCatalog::agendaPriorities()))],
            'effort_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'active' => ['nullable', 'boolean'],
        ]);

        $recurrence = $data['recurrence'];

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'work_function' => $data['work_function'],
            'recurrence' => $recurrence,
            'anchor_weekday' => match ($recurrence) {
                'monday' => Carbon::MONDAY,
                'weekly' => (int) $data['anchor_weekday'],
                default => null,
            },
            'anchor_day' => in_array($recurrence, ['monthly', 'bimonthly'], true) ? (int) $data['anchor_day'] : null,
            'due_time' => $data['due_time'],
            'priority' => $data['priority'],
            'effort_hours' => $data['effort_hours'] ?? null,
            'active' => $request->boolean('active'),
        ];
    }

    private function assertCanUpdateItem(User $actor, AgendaItem $item): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($item->work_function) {
            abort_unless($actor->work_function === $item->work_function, 403);

            return;
        }

        abort_unless($actor->can(PopCatalog::PERMISSION_AGENDA), 403);
    }
}
