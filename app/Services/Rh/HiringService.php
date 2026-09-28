<?php

namespace App\Services\Rh;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\ProcessAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class HiringService
{
    public function __construct(private AttendanceNotifier $notifier) {}

    public function open(User $actor, Company $company, string $name, ?string $jobTitle = null, ?string $notes = null, ?string $admissionOn = null): Candidate
    {
        return Candidate::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'job_title' => $jobTitle,
            'status' => Candidate::STATUS_DOCS,
            'notes' => $notes,
            'admission_on' => $admissionOn,
        ]);
    }

    public function attachInSequence(Candidate $candidate, User $actor, string $kind, UploadedFile $file): Candidate
    {
        if (! $candidate->isOpen()) {
            throw ValidationException::withMessages(['file' => 'Esta contratação já foi encerrada.']);
        }

        $candidate->loadMissing('attachments');
        $pending = collect($candidate->documentQueue())->first(fn (array $document) => ($document['status'] ?? '') !== 'anexado');
        $allowed = $pending['kind'] ?? null;

        if ($kind === 'scale' && $candidate->inss_verified_at === null) {
            throw ValidationException::withMessages([
                'file' => 'A contabilidade ainda precisa verificar o registro no INSS.',
            ]);
        }

        if ($allowed !== $kind) {
            throw ValidationException::withMessages([
                'file' => 'Anexe primeiro: '.($pending['label'] ?? 'o documento da vez').'.',
            ]);
        }

        $path = $file->store('hiring/'.$candidate->id, 'local');

        ProcessAttachment::create([
            'candidate_id' => $candidate->id,
            'kind' => $kind,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $actor->id,
        ]);

        $previous = $candidate->status;
        $next = collect(Candidate::DOCUMENT_QUEUE)->firstWhere('kind', $kind)['next'] ?? $candidate->status;
        $candidate->update(['status' => $next]);
        $candidate = $candidate->fresh(['attachments', 'company']);

        if ($previous !== Candidate::STATUS_ACCOUNTING && $candidate->status === Candidate::STATUS_ACCOUNTING) {
            $this->handToAccounting($candidate);
        }

        return $candidate->fresh(['attachments', 'company']);
    }

    public function verifyInss(Candidate $candidate, User $actor): Candidate
    {
        if ($candidate->status !== Candidate::STATUS_ACCOUNTING) {
            throw ValidationException::withMessages(['status' => 'Este card não está na contabilidade.']);
        }

        if (! $candidate->admission_on) {
            throw ValidationException::withMessages(['admission_on' => 'Informe a data de admissão antes de verificar o registro.']);
        }

        $candidate->update([
            'inss_registered_at' => $candidate->inss_registered_at ?? now(),
            'inss_verified_at' => now(),
            'status' => Candidate::STATUS_STORE,
        ]);

        return $candidate->fresh(['attachments', 'company']);
    }

    private function handToAccounting(Candidate $candidate): void
    {
        if ($candidate->admission_on && $candidate->inss_registered_at === null) {
            $candidate->update(['inss_registered_at' => now()]);
            $candidate = $candidate->fresh();
        }

        $registered = $candidate->inss_registered_at
            ? ' A data de admissão gerou o registro automático. Falta a verificação.'
            : ' Falta a data de admissão para gerar o registro.';

        $this->notifier->notify(
            $this->notifier->accountingUsers(),
            'Contratação na contabilidade',
            $candidate->name.' chegou para registro no INSS.'.$registered,
            route('work.project', 'recruitment'),
        );
    }
}
