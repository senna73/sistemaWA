<?php

namespace App\Services\Rh;

use App\Models\OffboardingProcess;
use App\Mail\RhProcessMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Dossiê demissional: cópia com retenção de 2 anos, encaminhamento de ASO e aviso à loja.
 */
class OffboardingDocuments
{
    public function archiveAdmissionPack(OffboardingProcess $process): void
    {
        $process->loadMissing(['attachments', 'collaborator']);

        $dir = 'dossiers/'.$process->collaborator_id.'/'.$process->id;
        Storage::disk('local')->makeDirectory($dir);

        $copied = [];
        foreach ($process->attachments as $attachment) {
            if (! $attachment->existsOnDisk()) {
                continue;
            }

            $name = $attachment->kind.'_'.basename($attachment->path);
            Storage::disk('local')->copy($attachment->path, $dir.'/'.$name);
            $copied[] = [
                'kind' => $attachment->kind,
                'original_name' => $attachment->original_name,
                'file' => $name,
            ];
        }

        $years = max(1, (int) config('rh.dossier_retention_years', 2));
        $retentionUntil = now()->addYears($years);
        $manifest = [
            'process_id' => $process->id,
            'collaborator_id' => $process->collaborator_id,
            'collaborator_name' => $process->collaborator?->name,
            'archived_at' => now()->toDateTimeString(),
            'retention_until' => $retentionUntil->toDateString(),
            'files' => $copied,
        ];

        Storage::disk('local')->put($dir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $process->update([
            'dossier_path' => $dir,
            'dossier_archived_at' => now(),
            'dossier_retention_until' => $retentionUntil,
        ]);
    }

    public function requestDismissalAso(OffboardingProcess $process): void
    {
        $process->loadMissing(['collaborator', 'attachments']);
        $name = $process->collaborator?->name ?? 'colaborador';
        $kinds = $process->attachments->pluck('kind')->unique()->implode(', ') ?: 'nenhum anexo';

        $this->send(
            [config('rh.accounting_email')],
            "ASO / dossiê demissional — {$name}",
            "O processo #{$process->id} de {$name} foi encaminhado à contabilidade.\nAnexos: {$kinds}.",
            $process,
            'rh.aso_to_accounting',
        );
    }

    public function notifyEstablishments(OffboardingProcess $process): void
    {
        $process->loadMissing(['collaborator.homeCompany.coordinator']);
        $company = $process->collaborator?->homeCompany;
        $name = $process->collaborator?->name ?? 'colaborador';

        $this->send(
            array_filter([
                $company?->contact_email,
                $company?->coordinator?->email,
            ]),
            "Desligamento concluído — {$name}",
            "Informamos que o desligamento de {$name} foi concluído no processo #{$process->id}. "
            .'O dossiê fica retido por '.config('rh.dossier_retention_years', 2).' anos.',
            $process,
            'rh.notify_establishment',
        );
    }

    /**
     * @param  list<string|null>  $to
     */
    private function send(array $to, string $subject, string $body, OffboardingProcess $process, string $channel): void
    {
        $recipients = array_values(array_unique(array_filter($to)));

        Log::info($channel, [
            'process_id' => $process->id,
            'subject' => $subject,
            'to' => $recipients,
        ]);

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send(new RhProcessMail($process, $subject, $body));
    }
}
