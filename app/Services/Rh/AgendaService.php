<?php

namespace App\Services\Rh;

use App\Models\AgendaItem;
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
        $assignee = User::query()->findOrFail($data['assignee_id']);
        $this->assertAssignee($assignee);
        $this->assertDemandType($actor, $data);
        $links = $this->linksFrom($data);

        $item = AgendaItem::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assignee_id' => $assignee->id,
            'created_by' => $actor->id,
            'due_at' => $data['due_at'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'type' => $data['type'],
            'area' => $data['area'] ?? null,
            'status' => AgendaItem::STATUS_PENDING,
            'recurrence' => $data['recurrence'] ?? 'once',
            'collaborator_id' => $links['collaborator_id'],
            'offboarding_process_id' => $links['offboarding_process_id'],
            'candidate_id' => $links['candidate_id'],
            'company_id' => $links['company_id'],
            'payload' => $data['payload'] ?? null,
        ]);

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

            if ($item->recurrence !== 'once' && $item->due_at) {
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
        $query = AgendaItem::query()->whereBetween('due_at', [$from, $to]);
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
        foreach (['rh', 'super_admin', 'coordinator'] as $role) {
            $user = User::query()->where('role', $role)->where('active', true)->orderBy('id')->first();
            if ($user) {
                return $user;
            }
        }

        if ($fallback && in_array($fallback->role, PopCatalog::agendaAssigneeRoles(), true)) {
            return $fallback;
        }

        return null;
    }

    public function assertAssignee(User $assignee): void
    {
        if (! in_array($assignee->role, PopCatalog::agendaAssigneeRoles(), true) && ! $assignee->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'assignee_id' => 'A atividade só pode ser atribuída para pessoa do RH ou coordenador.',
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
