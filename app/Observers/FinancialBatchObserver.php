<?php

namespace App\Observers;

use App\Models\FinancialBatches;
use App\Services\Rh\AttendanceNotifier;

class FinancialBatchObserver
{
    public function __construct(private AttendanceNotifier $notifier) {}

    public function updated(FinancialBatches $batch): void
    {
        if (! $batch->wasChanged('status')) {
            return;
        }

        $from = FinancialBatches::attendanceStageLabelFor($batch->getOriginal('status'));
        $to = $batch->attendanceStageLabel();

        if ($from === $to) {
            return;
        }

        $batch->loadMissing('company');
        $store = $batch->company?->name ?? 'Lote #'.$batch->id;

        $this->notifier->notify(
            $this->notifier->owners(),
            'Atendimento financeiro',
            $store.' avançou de "'.$from.'" para "'.$to.'".',
            route('work.project', 'finance'),
        );
    }
}
