<?php

namespace App\Services\WhatsApp;

use App\Models\Collaborator;
use App\Models\OffboardingProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WhatsAppNotifier
{
    public function notifyOffboardingStage(OffboardingProcess $process, string $status, Collection $recipients): void
    {
        Log::info('whatsapp.offboarding_stage', [
            'process_id' => $process->id,
            'status' => $status,
            'recipient_ids' => $recipients->pluck('id')->all(),
        ]);
    }

    public function notifyInactivity(Collaborator $collaborator, int $days, Collection $recipients): void
    {
        Log::info('whatsapp.inactivity', [
            'collaborator_id' => $collaborator->id,
            'days' => $days,
            'recipient_ids' => $recipients->pluck('id')->all(),
        ]);
    }

    public function notifyCoordinatorOnly(OffboardingProcess $process, Collection $coordinators): void
    {
        $name = $process->collaborator?->name;
        Log::info('whatsapp.coordinator_received', [
            'process_id' => $process->id,
            'message' => "A solicitação de desligamento de {$name} foi recebida pelo RH e está em conferência.",
            'recipient_ids' => $coordinators->pluck('id')->all(),
        ]);
    }

    public function notifyCollaboratorAndCoordinator(OffboardingProcess $process, Collection $coordinators): void
    {
        Log::info('whatsapp.resignation_started', [
            'process_id' => $process->id,
            'collaborator_id' => $process->collaborator_id,
            'coordinator_ids' => $coordinators->pluck('id')->all(),
        ]);
    }

    public function notifyInactivity18Coordinator(Collaborator $collaborator, Collection $coordinators): void
    {
        Log::info('whatsapp.inactivity_18_coordinator', [
            'collaborator_id' => $collaborator->id,
            'message' => "{$collaborator->name} está há 18 dias sem realizar diária e permanece no seu grupo. Verifique a situação e registre a tratativa no sistema.",
            'recipient_ids' => $coordinators->pluck('id')->all(),
        ]);
    }

    public function notifyInactivity18Collaborator(Collaborator $collaborator): void
    {
        Log::info('whatsapp.inactivity_18_collaborator', [
            'collaborator_id' => $collaborator->id,
            'message' => "Olá, {$collaborator->name}. Identificamos que você está há 18 dias sem realizar diária conosco. Está viajando, com alguma questão de saúde ou outra dificuldade? Responda para que o RH registre sua situação.",
        ]);
    }

    public function notifyCollaboratorOffboardingStarted(OffboardingProcess $process): void
    {
        $name = $process->collaborator?->name;
        Log::info('whatsapp.collaborator_offboarding', [
            'process_id' => $process->id,
            'message' => "Olá, {$name}. Informamos que seu processo de desligamento está em andamento. O RH dará continuidade às próximas etapas e entrará em contato caso seja necessário.",
        ]);
    }

    public function notifyExamScheduled(OffboardingProcess $process): void
    {
        Log::info('whatsapp.exam_scheduled', [
            'process_id' => $process->id,
            'exam_at' => optional($process->exam_at)?->toDateTimeString(),
            'location' => $process->exam_location,
        ]);
    }

    public function notifyAccountingHandoff(OffboardingProcess $process): void
    {
        $name = $process->collaborator?->name;
        Log::info('whatsapp.accounting', [
            'process_id' => $process->id,
            'message' => "O processo de desligamento de {$name} está com a contabilidade. Agora aguardamos o retorno da documentação para continuar o processo.",
        ]);
    }

    public function notifyFinalDocuments(OffboardingProcess $process): void
    {
        Log::info('whatsapp.final_docs', ['process_id' => $process->id]);
    }

    public function notifyGroupMove(OffboardingProcess $process): void
    {
        Log::info('whatsapp.group_move', [
            'process_id' => $process->id,
            'new_company_id' => $process->new_company_id,
        ]);
    }

    public function removeFromStoreGroups(Collaborator $collaborator): array
    {
        $payload = [
            'collaborator_id' => $collaborator->id,
            'at' => now()->toDateTimeString(),
            'group' => $collaborator->group,
            'result' => 'logged_pending_provider',
        ];
        Log::info('whatsapp.remove_from_groups', $payload);

        return $payload;
    }
}
