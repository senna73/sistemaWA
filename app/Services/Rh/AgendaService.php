<?php

namespace App\Services\Rh;

use App\Models\AgendaItem;
use App\Models\AgendaSchedule;
use App\Models\Collaborator;
use App\Models\OperationalDemand;
use App\Models\User;
use App\Support\PopCatalog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AgendaService
{
    public function create(User $actor, array $data): AgendaItem
    {
        $assignee = ! empty($data['assignee_id']) ? User::query()->findOrFail($data['assignee_id']) : null;
        if ($assignee) {
            $this->assertAssignee($assignee);
        }
        $workFunction = $data['work_function'] ?? $assignee?->work_function;
        if ($assignee && $workFunction && $assignee->work_function && $assignee->work_function !== $workFunction) {
            throw ValidationException::withMessages([
                'assignee_id' => 'O responsável precisa ser do mesmo setor da atividade.',
            ]);
        }
        if (! $workFunction && ! $assignee) {
            throw ValidationException::withMessages([
                'work_function' => 'A atividade precisa de um setor.',
            ]);
        }
        if (in_array($data['type'] ?? '', ['operational_demand', 'collaborator_request'], true) && ! $assignee) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Demanda operacional precisa de um responsável.',
            ]);
        }
        $this->assertDemandType($actor, $data);
        $links = $this->linksFrom($data);

        $item = AgendaItem::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assignee_id' => $assignee?->id,
            'created_by' => $actor->id,
            'due_at' => $data['due_at'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'type' => $data['type'],
            'area' => $data['area'] ?? null,
            'status' => AgendaItem::STATUS_PENDING,
            'recurrence' => $data['recurrence'] ?? 'once',
            'work_function' => $workFunction,
            'collaborator_id' => $links['collaborator_id'],
            'offboarding_process_id' => $links['offboarding_process_id'],
            'candidate_id' => $links['candidate_id'],
            'company_id' => $links['company_id'],
            'payload' => $data['payload'] ?? null,
        ]);

        if ($assignee && $assignee->id !== $actor->id) {
            app(AttendanceNotifier::class)->notify(
                collect([$assignee]),
                'Nova atividade na agenda',
                $item->title.($item->due_at ? ' · '.$item->due_at->format('d/m H:i') : ''),
                route('agenda.board'),
            );
        } elseif ($workFunction && ! $assignee) {
            $this->notifyFunction(
                $workFunction,
                'Nova atividade na agenda',
                $item->title.($item->due_at ? ' · '.$item->due_at->format('d/m H:i') : ''),
            );
        }

        if (in_array($item->type, ['operational_demand', 'collaborator_request'], true) && empty($data['skip_demand'])) {
            OperationalDemand::query()->create([
                'opened_by' => $actor->id,
                'assigned_to' => $assignee->id,
                'agenda_item_id' => $item->id,
                'collaborator_id' => $item->collaborator_id,
                'name' => $data['demand_name'] ?? $item->collaborator?->name ?? $actor->name,
                'mobile' => $data['demand_mobile'] ?? $item->collaborator?->mobile ?? $actor->mobile,
                'category' => $data['category'] ?? 'solicitacao_loja',
                'request_text' => $item->title.($item->description ? "\n".$item->description : ''),
                'payload' => $item->payload,
                'status' => OperationalDemand::STATUS_AWAITING,
            ]);
        }

        return $item->fresh(['collaborator', 'company', 'candidate', 'process', 'demand']);
    }

    public function updateStatus(AgendaItem $item, User $actor, string $status): AgendaItem
    {
        $item->update(['status' => $status]);

        if ($status === AgendaItem::STATUS_DONE) {
            $this->fulfill($item->fresh(), $actor);

            if (! $item->agenda_schedule_id && $item->recurrence !== 'once' && $item->due_at) {
                $next = $this->nextDue($item->due_at, $item->recurrence);
                if ($next) {
                    AgendaItem::query()->create([
                        'title' => $item->title,
                        'description' => $item->description,
                        'assignee_id' => $item->assignee_id,
                        'created_by' => $actor->id,
                        'due_at' => $next,
                        'priority' => $item->priority,
                        'type' => $item->type,
                        'area' => $item->area,
                        'status' => AgendaItem::STATUS_PENDING,
                        'recurrence' => $item->recurrence,
                        'parent_id' => $item->id,
                        'collaborator_id' => $item->collaborator_id,
                        'offboarding_process_id' => $item->offboarding_process_id,
                        'candidate_id' => $item->candidate_id,
                        'company_id' => $item->company_id,
                        'payload' => $item->payload,
                    ]);
                }
            }
        }

        return $item->fresh(['collaborator', 'company', 'candidate', 'process']);
    }

    /**
     * @return array{today: Collection, week: Collection, later: Collection}
     */
    public function board(?User $user = null): array
    {
        $query = AgendaItem::query()
            ->with(['assignee', 'collaborator', 'company', 'candidate', 'process'])
            ->whereNull('work_function')
            ->whereNotIn('status', [AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED]);
        if ($user && ! $user->isSuperAdmin() && $user->isCoordinator()) {
            $query->where('assignee_id', $user->id);
        }

        $items = $query->orderBy('due_at')->get();
        $todayEnd = now()->endOfDay();
        $weekEnd = now()->copy()->addDays(7)->endOfDay();

        return [
            'today' => $items->filter(fn (AgendaItem $item) => $item->due_at && $item->due_at->lte($todayEnd)),
            'week' => $items->filter(fn (AgendaItem $item) => $item->due_at && $item->due_at->gt($todayEnd) && $item->due_at->lte($weekEnd)),
            'later' => $items->filter(fn (AgendaItem $item) => ! $item->due_at || $item->due_at->gt($weekEnd)),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function indicators(?User $user = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfDay();
        $to = $to ?? now()->endOfDay();
        $query = AgendaItem::query()->whereNull('work_function')->whereBetween('due_at', [$from, $to]);
        if ($user) {
            $query->where('assignee_id', $user->id);
        }

        $items = $query->get();

        return [
            'done_today' => $items->where('status', AgendaItem::STATUS_DONE)->count(),
            'in_progress' => $items->where('status', AgendaItem::STATUS_IN_PROGRESS)->count(),
            'pending' => $items->where('status', AgendaItem::STATUS_PENDING)->count(),
            'late' => $items->filter(fn (AgendaItem $item) => $item->effectiveStatus() === AgendaItem::STATUS_LATE)->count(),
            'finished' => $items->where('status', AgendaItem::STATUS_DONE)->count(),
            'recurring_done' => $items->where('status', AgendaItem::STATUS_DONE)->where('recurrence', '!=', 'once')->count(),
        ];
    }

    public function defaultAssignee(?User $fallback = null): ?User
    {
        $preferredEmail = (string) config('rh.agenda_assignee_email', '');
        if ($preferredEmail !== '') {
            $preferred = User::query()->where('email', $preferredEmail)->where('active', true)->first();
            if ($preferred && PopCatalog::canReceiveAgenda($preferred)) {
                return $preferred;
            }
        }

        foreach (['rh', 'super_admin', 'coordinator'] as $role) {
            $user = User::query()->where('role', $role)->where('active', true)->orderBy('id')->first();
            if ($user) {
                return $user;
            }
        }

        $withAgenda = User::query()->where('active', true)->orderBy('id')->get()
            ->first(fn (User $user) => PopCatalog::canReceiveAgenda($user));
        if ($withAgenda) {
            return $withAgenda;
        }

        if ($fallback && PopCatalog::canReceiveAgenda($fallback)) {
            return $fallback;
        }

        return null;
    }

    public function assertAssignee(User $assignee): void
    {
        if (! PopCatalog::canReceiveAgenda($assignee)) {
            throw ValidationException::withMessages([
                'assignee_id' => 'A atividade só pode ser atribuída para pessoa do RH, coordenador ou de uma função da agenda.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDemandType(User $actor, array $data): void
    {
        $type = $data['type'] ?? '';
        if (! in_array($type, ['operational_demand', 'collaborator_request'], true)) {
            return;
        }

        if ($actor->isRh() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'type' => 'O RH não abre demanda. Use a fila para atender o que chegar.',
            ]);
        }

        if ($type === 'collaborator_request' && ! $actor->isCollaboratorRole() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'type' => 'Solicitação do colaborador entra pelo portal.',
            ]);
        }

        if (! PopCatalog::canOpenOperationalDemand($actor) && ! $actor->isCollaboratorRole()) {
            throw ValidationException::withMessages([
                'type' => 'Só coordenador ou superadmin abrem demanda operacional.',
            ]);
        }

        $category = $data['category'] ?? 'solicitacao_loja';
        $allowed = PopCatalog::demandCategoriesFor($actor);
        if ($allowed !== [] && ! isset($allowed[$category])) {
            throw ValidationException::withMessages([
                'category' => 'Esta categoria não está disponível para o seu perfil.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{collaborator_id: ?int, offboarding_process_id: ?int, candidate_id: ?int, company_id: ?int}
     */
    private function linksFrom(array $data): array
    {
        $collaboratorId = isset($data['collaborator_id']) ? (int) $data['collaborator_id'] : null;
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;
        $collaborator = $collaboratorId ? Collaborator::query()->find($collaboratorId) : null;
        if ($collaborator && ! $companyId) {
            $companyId = $collaborator->home_company_id;
        }

        return [
            'collaborator_id' => $collaborator?->id,
            'offboarding_process_id' => isset($data['offboarding_process_id']) ? (int) $data['offboarding_process_id'] : null,
            'candidate_id' => isset($data['candidate_id']) ? (int) $data['candidate_id'] : null,
            'company_id' => $companyId,
        ];
    }

    private function fulfill(AgendaItem $item, User $actor): void
    {
        if ($item->type === 'offboarding_groups' && $item->offboarding_process_id) {
            app(OffboardingService::class)->completeGroupRemoval($item->process()->firstOrFail(), $actor);
        }
    }

    public function materialize(?Carbon $now = null): int
    {
        $now = $now ?? now();
        $from = $now->copy()->subDays(7)->startOfDay();
        $to = $now->copy()->addDays(7)->endOfDay();
        $created = 0;

        $schedules = AgendaSchedule::query()->where('active', true)->get();
        foreach ($schedules as $schedule) {
            foreach ($this->occurrences($schedule, $from, $to) as $date) {
                $exists = AgendaItem::query()
                    ->where('agenda_schedule_id', $schedule->id)
                    ->whereDate('occurrence_on', $date->toDateString())
                    ->exists();
                if ($exists) {
                    continue;
                }

                $item = AgendaItem::query()->create([
                    'agenda_schedule_id' => $schedule->id,
                    'occurrence_on' => $date->toDateString(),
                    'title' => $schedule->title,
                    'description' => $schedule->description,
                    'assignee_id' => null,
                    'created_by' => $schedule->created_by,
                    'due_at' => $this->dueOn($schedule, $date),
                    'priority' => $schedule->priority,
                    'effort_hours' => $schedule->effort_hours,
                    'type' => 'routine',
                    'status' => AgendaItem::STATUS_PENDING,
                    'recurrence' => 'once',
                    'work_function' => $schedule->work_function,
                ]);
                $created++;
                $this->notifyFunction(
                    $schedule->work_function,
                    'Nova atividade na agenda',
                    $item->title.($item->due_at ? ' · '.$item->due_at->format('d/m H:i') : ''),
                );
            }
        }

        return $created;
    }

    /**
     * @return array{scheduled: int, pending: int, in_progress: int, finished: int, late: int}
     */
    public function directorIndicators(Carbon $from, Carbon $to, ?string $function = null, ?int $assigneeId = null): array
    {
        $items = $this->functionItems($from, $to, $function, $assigneeId);
        $open = $items->reject(fn (AgendaItem $item) => $item->status === AgendaItem::STATUS_CANCELLED);

        return [
            'scheduled' => $this->scheduledCount($from, $to, $function),
            'pending' => $open->filter(fn (AgendaItem $item) => $item->status === AgendaItem::STATUS_PENDING && $item->effectiveStatus() !== AgendaItem::STATUS_LATE)->count(),
            'in_progress' => $open->filter(fn (AgendaItem $item) => in_array($item->status, [AgendaItem::STATUS_IN_PROGRESS, AgendaItem::STATUS_IN_REVIEW], true) && $item->effectiveStatus() !== AgendaItem::STATUS_LATE)->count(),
            'finished' => $items->where('status', AgendaItem::STATUS_DONE)->count(),
            'late' => $open->filter(fn (AgendaItem $item) => $item->effectiveStatus() === AgendaItem::STATUS_LATE)->count(),
        ];
    }

    /**
     * @return array{pending: Collection, in_progress: Collection, in_review: Collection}
     */
    public function functionBoard(Carbon $from, Carbon $to, ?string $function = null, ?int $assigneeId = null): array
    {
        $items = $this->functionItems($from, $to, $function, $assigneeId)
            ->reject(fn (AgendaItem $item) => in_array($item->status, [AgendaItem::STATUS_DONE, AgendaItem::STATUS_CANCELLED], true))
            ->sortBy(fn (AgendaItem $item) => $item->due_at?->timestamp ?? PHP_INT_MAX)
            ->values();

        return [
            'pending' => $items->reject(fn (AgendaItem $item) => in_array($item->status, [AgendaItem::STATUS_IN_PROGRESS, AgendaItem::STATUS_IN_REVIEW], true))->values(),
            'in_progress' => $items->where('status', AgendaItem::STATUS_IN_PROGRESS)->values(),
            'in_review' => $items->where('status', AgendaItem::STATUS_IN_REVIEW)->values(),
        ];
    }

    /**
     * @return Collection<int, array{user: User, received: int, done: int, late: int, percent: int, recent: Collection<int, AgendaItem>}>
     */
    public function productivity(Carbon $from, Carbon $to, ?string $function = null, ?int $assigneeId = null): Collection
    {
        $items = $this->functionItems($from, $to, $function, $assigneeId)
            ->reject(fn (AgendaItem $item) => $item->status === AgendaItem::STATUS_CANCELLED);
        $users = User::query()
            ->where('active', true)
            ->whereNotNull('work_function')
            ->when($function, fn ($query) => $query->where('work_function', $function))
            ->when($assigneeId, fn ($query) => $query->where('id', $assigneeId))
            ->orderBy('name')
            ->get();

        return $users->map(function (User $user) use ($items) {
            $mine = $items->where('assignee_id', $user->id);
            $received = $mine->count();
            $done = $mine->where('status', AgendaItem::STATUS_DONE)->count();

            return [
                'user' => $user,
                'received' => $received,
                'done' => $done,
                'late' => $mine->filter(fn (AgendaItem $item) => $item->effectiveStatus() === AgendaItem::STATUS_LATE)->count(),
                'percent' => $received === 0 ? 0 : (int) round(($done / $received) * 100),
                'recent' => $mine->sortByDesc(fn (AgendaItem $item) => $item->updated_at?->timestamp ?? 0)->take(3)->values(),
            ];
        });
    }

    public function assign(AgendaItem $item, User $actor, ?int $assigneeId): AgendaItem
    {
        if (! $item->work_function) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Só atividades de um setor podem ser transferidas por aqui.',
            ]);
        }

        $assignee = null;
        if ($assigneeId) {
            $assignee = User::query()->findOrFail($assigneeId);
            if (! $assignee->active || $assignee->work_function !== $item->work_function) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'O responsável precisa estar ativo e ser do mesmo setor.',
                ]);
            }
        }

        $item->update(['assignee_id' => $assignee?->id]);

        if ($assignee && $assignee->id !== $actor->id) {
            app(AttendanceNotifier::class)->notify(
                collect([$assignee]),
                'Atividade atribuída a você',
                $item->title,
                route('agenda.board'),
            );
        }

        return $item->fresh('assignee');
    }

    public function requestCorrection(AgendaItem $item, User $actor, string $note): AgendaItem
    {
        $item->update([
            'status' => AgendaItem::STATUS_IN_REVIEW,
            'correction_note' => $note,
        ]);

        $item = $item->fresh('assignee');
        if ($item->assignee && $item->assignee_id !== $actor->id) {
            app(AttendanceNotifier::class)->notify(
                collect([$item->assignee]),
                'Correção pedida na atividade',
                $item->title.' · '.$note,
                route('agenda.board'),
            );
        } elseif ($item->work_function) {
            $this->notifyFunction($item->work_function, 'Correção pedida na atividade', $item->title.' · '.$note);
        }

        return $item;
    }

    /**
     * @return Collection<int, array{key: string, label: string, items: Collection<int, AgendaItem>, upcoming: Collection<int, array{title: string, due_at: Carbon, schedule: AgendaSchedule}>}>
     */
    public function plannedBySector(Carbon $from, Carbon $to, ?string $function = null): Collection
    {
        $catalog = $function
            ? [$function => PopCatalog::workFunctions()[$function] ?? $function]
            : PopCatalog::workFunctions();
        $items = $this->functionItems($from, $to, $function);

        return collect($catalog)->map(function (string $label, string $key) use ($items, $from, $to) {
            $upcoming = collect();
            $schedules = AgendaSchedule::query()
                ->where('active', true)
                ->where('work_function', $key)
                ->orderBy('title')
                ->get();
            foreach ($schedules as $schedule) {
                foreach ($this->occurrences($schedule, $from, $to) as $date) {
                    $exists = AgendaItem::query()
                        ->where('agenda_schedule_id', $schedule->id)
                        ->whereDate('occurrence_on', $date->toDateString())
                        ->exists();
                    if (! $exists) {
                        $upcoming->push([
                            'title' => $schedule->title,
                            'due_at' => $this->dueOn($schedule, $date),
                            'schedule' => $schedule,
                        ]);
                    }
                }
            }

            return [
                'key' => $key,
                'label' => $label,
                'items' => $items->where('work_function', $key)->sortBy(fn (AgendaItem $item) => $item->due_at?->timestamp ?? PHP_INT_MAX)->values(),
                'upcoming' => $upcoming->sortBy(fn (array $row) => $row['due_at']->timestamp)->values(),
            ];
        })->values();
    }

    /**
     * @return list<Carbon>
     */
    public function occurrences(AgendaSchedule $schedule, Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfDay();
        $notBefore = ($schedule->created_at ?? $start)->copy()->startOfDay();
        if ($start->lt($notBefore)) {
            $start = $notBefore;
        }

        $end = $to->copy()->startOfDay();
        if ($start->gt($end)) {
            return [];
        }

        $dates = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if ($this->occurrenceMatches($schedule, $cursor)) {
                $dates[] = $cursor->copy();
            }
        }

        return $dates;
    }

    private function scheduledCount(Carbon $from, Carbon $to, ?string $function): int
    {
        $count = 0;
        $schedules = AgendaSchedule::query()
            ->where('active', true)
            ->when($function, fn ($query) => $query->where('work_function', $function))
            ->get();

        foreach ($schedules as $schedule) {
            foreach ($this->occurrences($schedule, $from, $to) as $date) {
                $exists = AgendaItem::query()
                    ->where('agenda_schedule_id', $schedule->id)
                    ->whereDate('occurrence_on', $date->toDateString())
                    ->exists();
                if (! $exists) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function functionItems(Carbon $from, Carbon $to, ?string $function, ?int $assigneeId = null): Collection
    {
        return AgendaItem::query()
            ->with('assignee')
            ->whereNotNull('work_function')
            ->when($function, fn ($query) => $query->where('work_function', $function))
            ->when($assigneeId, fn ($query) => $query->where('assignee_id', $assigneeId))
            ->whereBetween('due_at', [$from, $to])
            ->get();
    }

    private function occurrenceMatches(AgendaSchedule $schedule, Carbon $day): bool
    {
        return match ($schedule->recurrence) {
            'daily' => true,
            'monday' => $day->dayOfWeekIso === Carbon::MONDAY,
            'weekly' => $day->dayOfWeekIso === (int) ($schedule->anchor_weekday ?: Carbon::MONDAY),
            'monthly' => $this->monthDayMatches($schedule, $day),
            'bimonthly' => $this->monthDayMatches($schedule, $day) && $this->bimonthStep($schedule, $day),
            default => false,
        };
    }

    private function monthDayMatches(AgendaSchedule $schedule, Carbon $day): bool
    {
        $anchor = min(max((int) ($schedule->anchor_day ?: 1), 1), $day->daysInMonth);

        return $day->day === $anchor;
    }

    private function bimonthStep(AgendaSchedule $schedule, Carbon $day): bool
    {
        $origin = ($schedule->created_at ?? $day)->copy()->startOfMonth();
        $months = (int) $origin->diffInMonths($day->copy()->startOfMonth(), true);

        return $months % 2 === 0;
    }

    private function dueOn(AgendaSchedule $schedule, Carbon $day): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', (string) $schedule->due_time), 2, '0');

        return $day->copy()->setTime((int) $hour, (int) $minute);
    }

    private function notifyFunction(string $function, string $title, string $body): void
    {
        $users = User::query()->where('active', true)->where('work_function', $function)->get();
        if ($users->isEmpty()) {
            return;
        }

        app(AttendanceNotifier::class)->notify($users, $title, $body, route('agenda.board'));
    }

    private function nextDue(Carbon $due, string $recurrence): ?Carbon
    {
        return match ($recurrence) {
            'monday' => $due->copy()->next(Carbon::MONDAY),
            'weekly' => $due->copy()->addWeek(),
            'monthly' => $due->copy()->addMonth(),
            'bimonthly' => $due->copy()->addMonths(2),
            'daily' => $due->copy()->addDay(),
            default => null,
        };
    }
}
