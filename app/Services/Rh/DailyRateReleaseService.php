<?php

namespace App\Services\Rh;

use App\Models\Collaborator;
use App\Models\DailyRateReleaseRequest;
use App\Models\FinancialBatches;
use App\Models\InactivityAudit;
use App\Models\OffboardingProcess;
use App\Models\RhTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyRateReleaseService
{
    public function __construct(private OffboardingService $offboarding) {}

    /**
     * @return array{code:string,message:string}|null
     */
    public function blockReason(int $collaboratorId, mixed $start = null): ?array
    {
        $collaborator = Collaborator::query()->find($collaboratorId);
        if (! $collaborator) {
            return null;
        }

        $at = $start ? Carbon::parse($start) : now();
        if ($this->usableRelease($collaborator, $at)) {
            return null;
        }

        if (! $collaborator->active) {
            return [
                'code' => 'inactive',
                'message' => 'Não é possível lançar diária: este colaborador está inativo. Solicite liberação ao RH ou à Direção.',
            ];
        }

        if (OffboardingProcess::blocksDailyRatesFor($collaboratorId)) {
            return [
                'code' => 'blocked',
                'message' => 'Não é possível lançar diária: este colaborador está em demissão, inativo ou já foi desligado. Solicite liberação ao RH ou à Direção.',
            ];
        }

        return null;
    }

    public function request(User $actor, Collaborator $collaborator, array $data): DailyRateReleaseRequest
    {
        return DailyRateReleaseRequest::query()->create([
            'collaborator_id' => $collaborator->id,
            'company_id' => $data['company_id'] ?? null,
            'requested_by' => $actor->id,
            'daily_on' => $data['daily_on'],
            'window_hours' => (int) ($data['window_hours'] ?? 48),
            'reason' => $data['reason'],
            'status' => DailyRateReleaseRequest::STATUS_PENDING,
        ]);
    }

    public function decide(DailyRateReleaseRequest $request, User $actor, string $decision, ?string $notes = null): DailyRateReleaseRequest
    {
        if ($request->status !== DailyRateReleaseRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Este pedido já foi decidido.']);
        }

        if (! $this->canApprove($actor)) {
            throw ValidationException::withMessages(['status' => 'Só RH, Direção ou Super admin aprovam a liberação.']);
        }

        $request->update([
            'status' => $decision === 'approve'
                ? DailyRateReleaseRequest::STATUS_APPROVED
                : DailyRateReleaseRequest::STATUS_REFUSED,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'notes' => $notes,
            'window_hours' => $request->window_hours ?: 48,
        ]);

        return $request->fresh();
    }

    public function afterDailyCreated(int $collaboratorId, mixed $start): void
    {
        $collaborator = Collaborator::query()->find($collaboratorId);
        if (! $collaborator) {
            return;
        }

        $at = Carbon::parse($start);
        $release = $this->usableRelease($collaborator, $at);
        if ($release) {
            $release->update([
                'status' => DailyRateReleaseRequest::STATUS_USED,
                'used_at' => now(),
            ]);
        }

        $this->reactivateAfterDaily($collaborator);
    }

    public function reactivateAfterDaily(Collaborator $collaborator): void
    {
        DB::transaction(function () use ($collaborator) {
            if (! $collaborator->active) {
                $collaborator->update(['active' => true]);
            }

            InactivityAudit::query()
                ->where('collaborator_id', $collaborator->id)
                ->whereIn('status', [
                    InactivityAudit::STATUS_OPEN_18,
                    InactivityAudit::STATUS_WATCH,
                    InactivityAudit::STATUS_OPEN_25,
                ])
                ->update([
                    'status' => InactivityAudit::STATUS_CLOSED,
                    'closed_at' => now(),
                ]);

            RhTask::query()
                ->where('collaborator_id', $collaborator->id)
                ->whereIn('type', [RhTask::TYPE_INACTIVITY, RhTask::TYPE_INACTIVITY_18])
                ->where('status', RhTask::STATUS_PENDING)
                ->update([
                    'status' => RhTask::STATUS_DONE,
                    'completed_at' => now(),
                    'notes' => 'Diária nova após inatividade.',
                ]);

            $process = OffboardingProcess::query()
                ->where('collaborator_id', $collaborator->id)
                ->where('kind', OffboardingProcess::KIND_INACTIVITY)
                ->whereIn('status', OffboardingProcess::OPEN_STATUSES)
                ->latest('id')
                ->first();

            if ($process) {
                $system = User::query()->orderBy('id')->first();
                if ($system) {
                    $this->offboarding->cancel($process, $system, 'Diária nova após inatividade.');
                }
            }
        });
    }

    public function canApprove(User $actor): bool
    {
        return $actor->isSuperAdmin()
            || $actor->can(\App\Support\AccessControl::PERMISSION_MANAGE_OFFBOARDING)
            || $actor->can(\App\Support\AccessControl::PERMISSION_DIRECTION);
    }

    public function usableRelease(Collaborator $collaborator, Carbon $at): ?DailyRateReleaseRequest
    {
        $day = $at->copy()->startOfDay()->toDateString();

        $releases = DailyRateReleaseRequest::query()
            ->where('collaborator_id', $collaborator->id)
            ->where('status', DailyRateReleaseRequest::STATUS_APPROVED)
            ->whereDate('daily_on', $day)
            ->latest('id')
            ->get();

        foreach ($releases as $release) {
            if ($release->reviewed_at && $at->gt($release->reviewed_at->copy()->addHours((int) $release->window_hours))) {
                $release->update(['status' => DailyRateReleaseRequest::STATUS_EXPIRED]);
                continue;
            }

            return $release;
        }

        return null;
    }

    public function paymentInProgress(Collaborator $collaborator): bool
    {
        $companyIds = $collaborator->dailyRates()->where('active', true)->pluck('company_id')->filter()->unique();
        if ($companyIds->isEmpty()) {
            return false;
        }

        return FinancialBatches::query()
            ->whereIn('company_id', $companyIds)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }
}
