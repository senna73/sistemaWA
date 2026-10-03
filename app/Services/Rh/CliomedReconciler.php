<?php

namespace App\Services\Rh;

use App\Models\Collaborator;
use App\Models\OffboardingProcess;
use App\Support\PersonName;

class CliomedReconciler
{
    /**
     * @param  list<array{name:string,unit:?string,sector:?string,role:?string}>  $report
     * @return array<string, mixed>
     */
    public function compare(array $report): array
    {
        $collaborators = Collaborator::query()->with('medicalClinic')->orderBy('id')->get();
        $unused = $collaborators->keyBy('id');

        $matched = [];
        $onlyReport = [];
        $wrongClinic = [];
        $inactiveInReport = [];
        $ambiguous = [];

        foreach ($report as $row) {
            $hits = $this->candidates($row['name'], $unused);

            if (count($hits) > 1) {
                $activeHits = $hits->where('active', true);
                if ($activeHits->count() === 1) {
                    $hits = $activeHits;
                } elseif ($activeHits->count() > 1) {
                    $sorted = $activeHits->sortByDesc(function (Collaborator $c) {
                        $stamp = $c->lastDailyAt();

                        return $stamp ? $stamp->timestamp : 0;
                    })->values();
                    $first = $sorted->first();
                    $second = $sorted->get(1);
                    $firstDaily = $first?->lastDailyAt();
                    $secondDaily = $second?->lastDailyAt();
                    if ($firstDaily && (! $secondDaily || $firstDaily->gt($secondDaily))) {
                        $hits = collect([$first]);
                    } else {
                        $ambiguous[] = [
                            'name' => $row['name'],
                            'sector' => $row['sector'],
                            'role' => $row['role'],
                            'candidates' => $activeHits->map(fn (Collaborator $c) => $this->snapshot($c))->values()->all(),
                        ];
                        continue;
                    }
                }
            }

            if ($hits->count() > 1) {
                $sorted = $hits->sortByDesc(function (Collaborator $c) {
                    $stamp = $c->lastDailyAt();

                    return $stamp ? $stamp->timestamp : 0;
                });
                $hits = collect([$sorted->first()]);
            }

            if ($hits->isEmpty()) {
                $onlyReport[] = [
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'sector' => $row['sector'],
                    'role' => $row['role'],
                ];
                continue;
            }

            $collaborator = $hits->first();
            $unused->forget($collaborator->id);
            $matched[] = [
                'id' => $collaborator->id,
                'name' => $collaborator->name,
                'report_name' => $row['name'],
                'clinic' => $collaborator->clinicSlug(),
                'active' => (bool) $collaborator->active,
            ];

            if (! $collaborator->active) {
                $inactiveInReport[] = [
                    'id' => $collaborator->id,
                    'name' => $collaborator->name,
                    'report_name' => $row['name'],
                ];
            }

            if ($collaborator->clinicSlug() !== OffboardingProcess::CLINIC_CLIOMED) {
                $wrongClinic[] = [
                    'id' => $collaborator->id,
                    'name' => $collaborator->name,
                    'report_name' => $row['name'],
                    'clinic' => $collaborator->clinicSlug() ?: 'sem clínica',
                ];
            }
        }

        $onlySystem = $unused
            ->filter(fn (Collaborator $c) => $c->active && $c->clinicSlug() === OffboardingProcess::CLINIC_CLIOMED)
            ->map(fn (Collaborator $c) => $this->snapshot($c))
            ->values()
            ->all();

        $okPeople = collect($matched)
            ->filter(fn (array $row) => $row['active'] && $row['clinic'] === OffboardingProcess::CLINIC_CLIOMED)
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'report_name' => $row['report_name'],
            ])
            ->values()
            ->all();

        return [
            'report_count' => count($report),
            'wa_cliomed' => $collaborators->filter(fn (Collaborator $c) => $c->clinicSlug() === OffboardingProcess::CLINIC_CLIOMED)->count(),
            'matched' => count($matched),
            'ok' => count($okPeople),
            'ok_people' => $okPeople,
            'only_report' => $onlyReport,
            'only_system' => $onlySystem,
            'wrong_clinic' => $wrongClinic,
            'inactive_in_report' => $inactiveInReport,
            'ambiguous' => $ambiguous,
            'inconsistency_count' => count($onlyReport) + count($onlySystem) + count($wrongClinic) + count($inactiveInReport) + count($ambiguous),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Collaborator>  $pool
     * @return \Illuminate\Support\Collection<int, Collaborator>
     */
    private function candidates(string $name, $pool)
    {
        $key = PersonName::key($name);
        $exact = $pool->filter(fn (Collaborator $c) => PersonName::key($c->name) === $key);
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return $pool->filter(fn (Collaborator $c) => PersonName::tokensFit($name, $c->name));
    }

    /**
     * @return array{id:int,name:string,clinic:?string,active:bool}
     */
    private function snapshot(Collaborator $collaborator): array
    {
        return [
            'id' => $collaborator->id,
            'name' => $collaborator->name,
            'clinic' => $collaborator->clinicSlug(),
            'active' => (bool) $collaborator->active,
        ];
    }
}
