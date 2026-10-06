<?php

namespace App\Services\Rh;

use App\Models\AgendaItem;
use App\Models\Collaborator;
use App\Models\OperationalDemand;
use App\Models\OperationalDemandAttachment;
use App\Models\OperationalDemandEvent;
use App\Models\User;
use App\Support\PopCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalDemandService
{
    /**
     * @return array<string, mixed>
     */
    public function typeBoard(User $actor, bool $queueOnly = true): array
    {
        $canHandle = $actor->can(PopCatalog::PERMISSION_HANDLE_DEMAND)
            || $actor->can(PopCatalog::PERMISSION_REVIEW_DEMAND)
            || $actor->isSuperAdmin();
        $canOperate = $canHandle && $actor->isRh();

        $query = OperationalDemand::query()
            ->with(['opener', 'assignee', 'collaborator.homeCompany', 'attachments', 'events.user', 'agendaItem'])
            ->latest('id');

        if (! $canHandle) {
            $query->where(function ($inner) use ($actor) {
                $inner->where('opened_by', $actor->id)->orWhere('assigned_to', $actor->id);
            });
        }

        if ($queueOnly) {
            $query->where('status', '!=', OperationalDemand::STATUS_DONE);
        }

        $demands = $query->get();

        return $this->boardFrom($demands, $canOperate);
    }

    /**
     * @param  Collection<int, OperationalDemand>  $demands
     * @return array<string, mixed>
     */
    public function boardFrom(Collection $demands, bool $canOperate = false): array
    {
        $columns = [];
        foreach (PopCatalog::demandCategories() as $key => $name) {
            $columns[$key] = [
                'id' => $key,
                'name' => $name,
                'city' => null,
                'cards' => [],
            ];
        }

        foreach ($demands as $demand) {
            $key = $demand->category;
            if (! isset($columns[$key])) {
                $columns[$key] = [
                    'id' => $key,
                    'name' => $demand->categoryLabel(),
                    'city' => null,
                    'cards' => [],
                ];
            }
            $columns[$key]['cards'][] = $demand->boardCard($canOperate);
        }

        $columnList = collect(array_values($columns))
            ->filter(fn (array $column) => $column['cards'] !== [])
            ->values();
        $cardCount = (int) $columnList->sum(fn (array $column) => count($column['cards']));

        return [
            'columns' => $columnList,
            'store_count' => $columnList->count(),
            'openings' => 0,
            'opening_count' => $cardCount,
            'excess_count' => 0,
            'process_count' => $cardCount,
            'summary' => [
                'awaiting' => $demands->where('status', OperationalDemand::STATUS_AWAITING)->count(),
                'in_progress' => $demands->where('status', OperationalDemand::STATUS_IN_PROGRESS)->count(),
                'review' => $demands->where('status', OperationalDemand::STATUS_REVIEW)->count(),
                'done' => $demands->where('status', OperationalDemand::STATUS_DONE)->count(),
            ],
        ];
    }

    public function open(User $actor, array $data, array $files = [], ?Collaborator $collaborator = null): OperationalDemand
    {
        if (count($files) > 3) {
            throw ValidationException::withMessages(['attachments' => 'No máximo 3 anexos.']);
        }

        if (! $actor->isCollaboratorRole() && ! PopCatalog::canOpenOperationalDemand($actor)) {
            throw ValidationException::withMessages([
                'category' => 'O RH não abre demanda. Coordenador e superadmin encaminham o pedido.',
            ]);
        }

        $allowed = $actor->isCollaboratorRole()
            ? PopCatalog::collaboratorRequestCategories()
            : PopCatalog::demandCategoriesFor($actor);
        if ($allowed !== [] && ! isset($allowed[$data['category']])) {
            throw ValidationException::withMessages([
                'category' => 'Esta categoria não está disponível para o seu perfil.',
            ]);
        }

        return DB::transaction(function () use ($actor, $data, $files, $collaborator) {
            $collaborator = $collaborator
                ?? (isset($data['collaborator_id']) ? Collaborator::query()->find($data['collaborator_id']) : null)
                ?? ($actor->isCollaboratorRole() ? $actor->collaborator : null);
            $required = PopCatalog::demandRecordLinks()[$data['category']] ?? [];
            if (in_array('collaborator', $required, true) && ! $collaborator) {
                throw ValidationException::withMessages([
                    'collaborator_id' => 'Esta solicitação precisa estar vinculada a um colaborador.',
                ]);
            }

            $assignee = app(AgendaService::class)->defaultAssignee($actor);
            $payload = $data['payload'] ?? PopCatalog::payloadFromInput($data['category'], $data);
            $title = PopCatalog::demandCategories()[$data['category']].($collaborator ? ' — '.$collaborator->name : '');

            $demand = OperationalDemand::query()->create([
                'collaborator_id' => $collaborator?->id,
                'opened_by' => $actor->id,
                'assigned_to' => $assignee?->id,
                'name' => $data['name'] ?? $collaborator?->name ?? $actor->name,
                'mobile' => $data['mobile'] ?? $collaborator?->mobile,
                'category' => $data['category'],
                'request_text' => $data['request_text'],
                'payload' => $payload,
                'status' => OperationalDemand::STATUS_AWAITING,
                'needs_review' => true,
            ]);

            if ($assignee) {
                $item = app(AgendaService::class)->create($actor, [
                    'title' => $title,
                    'description' => $data['request_text'],
                    'assignee_id' => $assignee->id,
                    'type' => $actor->isCollaboratorRole() ? 'collaborator_request' : 'operational_demand',
                    'due_at' => now()->addDay(),
                    'collaborator_id' => $collaborator?->id,
                    'company_id' => $collaborator?->home_company_id,
                    'category' => $data['category'],
                    'payload' => $payload,
                    'demand_name' => $demand->name,
                    'demand_mobile' => $demand->mobile,
                    'skip_demand' => true,
                ]);
                $demand->update(['agenda_item_id' => $item->id]);
            }

            $this->event($demand, $actor, 'opened', $data['request_text']);

            foreach (array_slice($files, 0, 3) as $file) {
                if ($file instanceof UploadedFile) {
                    $this->attach($demand, $actor, $file);
                }
            }

            return $demand->fresh(['attachments', 'events', 'agendaItem']);
        });
    }

    public function start(OperationalDemand $demand, User $actor): OperationalDemand
    {
        $demand->update([
            'status' => OperationalDemand::STATUS_IN_PROGRESS,
            'assigned_to' => $actor->id,
        ]);
        $this->event($demand, $actor, 'in_progress');

        return $demand->fresh();
    }

    public function note(OperationalDemand $demand, User $actor, string $notes, ?UploadedFile $file = null): OperationalDemand
    {
        $this->event($demand, $actor, 'note', $notes);
        if ($file) {
            $this->attach($demand, $actor, $file);
        }

        return $demand->fresh(['attachments', 'events']);
    }

    public function sendToReview(OperationalDemand $demand, User $actor): OperationalDemand
    {
        if ($this->needsApply($demand) && ! $this->wasApplied($demand)) {
            throw ValidationException::withMessages([
                'status' => 'Confirme a alteração no cadastro neste card antes de enviar para conferência.',
            ]);
        }

        $demand->update(['status' => OperationalDemand::STATUS_REVIEW]);
        $this->event($demand, $actor, 'review');

        return $demand->fresh();
    }

    public function returnToProgress(OperationalDemand $demand, User $actor, ?string $notes = null): OperationalDemand
    {
        $demand->update(['status' => OperationalDemand::STATUS_IN_PROGRESS]);
        $this->event($demand, $actor, 'returned', $notes);

        return $demand->fresh();
    }

    public function finish(OperationalDemand $demand, User $actor, ?string $notes = null): OperationalDemand
    {
        if ($demand->status !== OperationalDemand::STATUS_REVIEW) {
            throw ValidationException::withMessages(['status' => 'A conferência é obrigatória antes de finalizar.']);
        }

        if (! $actor->can(PopCatalog::PERMISSION_REVIEW_DEMAND) && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['status' => 'A conferência final é obrigatória antes de finalizar.']);
        }

        $demand->update([
            'status' => OperationalDemand::STATUS_DONE,
            'needs_review' => false,
        ]);
        if (! $this->wasApplied($demand)) {
            $this->applyOutcome($demand);
        }
        $this->event($demand, $actor, 'done', $notes);

        $demand->load('agendaItem');
        if ($demand->agendaItem && $demand->agendaItem->status !== AgendaItem::STATUS_DONE) {
            $demand->agendaItem->update(['status' => AgendaItem::STATUS_DONE]);
        }

        return $demand->fresh();
    }

    public function applyFromCard(OperationalDemand $demand, User $actor, array $input = []): OperationalDemand
    {
        if ($demand->status !== OperationalDemand::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => 'Atenda a demanda antes de gravar o cadastro.',
            ]);
        }

        if (! $this->needsApply($demand)) {
            throw ValidationException::withMessages([
                'category' => 'Este tipo de demanda não altera cadastro pelo card.',
            ]);
        }

        $collaborator = $demand->collaborator;
        if (! $collaborator) {
            throw ValidationException::withMessages([
                'collaborator_id' => 'Esta demanda precisa de um colaborador vinculado.',
            ]);
        }

        $incoming = PopCatalog::payloadFromInput($demand->category, $input) ?? [];
        $payload = array_merge($demand->payload ?? [], $incoming);
        $this->assertPixUnlocked($demand, $collaborator, $payload);
        $this->writeCollaboratorFromPayload($collaborator, $demand->category, $payload);

        $payload['applied'] = true;
        $payload['applied_at'] = now()->toIso8601String();
        $demand->update(['payload' => $payload]);
        $this->event($demand, $actor, 'applied', $this->appliedNote($demand->category, $payload));

        return $demand->fresh(['collaborator', 'events.user', 'attachments', 'agendaItem']);
    }

    private function applyOutcome(OperationalDemand $demand): void
    {
        $collaborator = $demand->collaborator;
        if (! $collaborator || ! $this->needsApply($demand)) {
            return;
        }

        $payload = $demand->payload ?? [];
        $this->assertPixUnlocked($demand, $collaborator, $payload);
        $this->writeCollaboratorFromPayload($collaborator, $demand->category, $payload);
        $payload['applied'] = true;
        $payload['applied_at'] = now()->toIso8601String();
        $demand->update(['payload' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeCollaboratorFromPayload(Collaborator $collaborator, string $category, array $payload): void
    {
        $fields = PopCatalog::demandApplyFields()[$category] ?? [];
        $updates = [];
        foreach ($fields as $field) {
            if (filled($payload[$field] ?? null)) {
                $updates[$field] = $payload[$field];
            }
        }

        if ($updates === []) {
            throw ValidationException::withMessages([
                'payload' => 'Informe o valor que deve ir para o cadastro.',
            ]);
        }

        $collaborator->update($updates);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertPixUnlocked(OperationalDemand $demand, Collaborator $collaborator, array $payload): void
    {
        if ($demand->category !== 'troca_pix' || blank($payload['pix_key'] ?? null)) {
            return;
        }

        $releases = app(DailyRateReleaseService::class);
        if ($releases->paymentInProgress($collaborator)) {
            throw ValidationException::withMessages([
                'pix_key' => 'Pagamento em andamento: não aplique a chave até o lote fechar.',
            ]);
        }
    }

    private function needsApply(OperationalDemand $demand): bool
    {
        return isset(PopCatalog::demandApplyFields()[$demand->category]);
    }

    private function wasApplied(OperationalDemand $demand): bool
    {
        return (bool) (($demand->payload ?? [])['applied'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function appliedNote(string $category, array $payload): string
    {
        $fields = PopCatalog::demandApplyFields()[$category] ?? [];
        $parts = [];
        foreach ($fields as $field) {
            if (filled($payload[$field] ?? null)) {
                $parts[] = $field.'='.$payload[$field];
            }
        }

        return $parts === [] ? 'Cadastro atualizado pelo card.' : implode('; ', $parts);
    }

    public function attach(OperationalDemand $demand, User $actor, UploadedFile $file): OperationalDemandAttachment
    {
        if ($demand->attachments()->count() >= 3 && $demand->wasRecentlyCreated === false) {
            $openCount = $demand->attachments()->count();
            if ($openCount >= 3) {
                throw ValidationException::withMessages(['attachments' => 'No máximo 3 anexos.']);
            }
        }

        $path = $file->store('demands/'.$demand->id, 'local');

        return OperationalDemandAttachment::query()->create([
            'operational_demand_id' => $demand->id,
            'uploaded_by' => $actor->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    private function event(OperationalDemand $demand, User $actor, string $event, ?string $notes = null): void
    {
        OperationalDemandEvent::query()->create([
            'operational_demand_id' => $demand->id,
            'user_id' => $actor->id,
            'event' => $event,
            'notes' => $notes,
        ]);
    }
}
