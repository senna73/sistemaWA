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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalDemandService
{
    public function open(User $actor, array $data, array $files = [], ?Collaborator $collaborator = null): OperationalDemand
    {
        if (count($files) > 3) {
            throw ValidationException::withMessages(['attachments' => 'No máximo 3 anexos.']);
        }

        return DB::transaction(function () use ($actor, $data, $files, $collaborator) {
            $collaborator = $collaborator
                ?? (isset($data['collaborator_id']) ? Collaborator::query()->find($data['collaborator_id']) : null)
                ?? $actor->collaborator;
            $required = PopCatalog::demandRecordLinks()[$data['category']] ?? [];
            if (in_array('collaborator', $required, true) && ! $collaborator) {
                throw ValidationException::withMessages([
                    'collaborator_id' => 'Esta solicitação precisa estar vinculada a um colaborador.',
                ]);
            }

            $assignee = app(AgendaService::class)->defaultAssignee($actor);
            $payload = $data['payload'] ?? null;
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
        $this->applyOutcome($demand);
        $this->event($demand, $actor, 'done', $notes);

        $demand->load('agendaItem');
        if ($demand->agendaItem && $demand->agendaItem->status !== AgendaItem::STATUS_DONE) {
            $demand->agendaItem->update(['status' => AgendaItem::STATUS_DONE]);
        }

        return $demand->fresh();
    }

    private function applyOutcome(OperationalDemand $demand): void
    {
        $collaborator = $demand->collaborator;
        if (! $collaborator) {
            return;
        }

        $payload = $demand->payload ?? [];
        if ($demand->category === 'troca_pix' && filled($payload['pix_key'] ?? null)) {
            $collaborator->update(['pix_key' => $payload['pix_key']]);
        }
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
