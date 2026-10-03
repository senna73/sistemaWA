<?php

namespace App\Services\Rh;

use App\Models\AccountingListRow;
use App\Models\Collaborator;
use App\Support\PersonName;
use Illuminate\Support\Collection;

class AccountingReconciler
{
    /**
     * @param  list<array{code:string,name:string,admission_on:string}>  $list
     * @return list<array<string, mixed>>
     */
    public function compare(array $list): array
    {
        $pool = Collaborator::query()
            ->orderBy('id')
            ->get();

        $used = [];
        $rows = [];

        foreach ($list as $item) {
            $hits = $this->candidates($item, $pool, $used);

            if ($hits->count() > 1) {
                $hits = $this->breakTie($hits, $item['admission_on']);
            }

            if ($hits->count() > 1) {
                $rows[] = $this->ambiguous($item, $hits);
                continue;
            }

            if ($hits->isEmpty()) {
                $rows[] = [
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'admission_on' => $item['admission_on'],
                    'bucket' => AccountingListRow::BUCKET_ONLY_LIST,
                    'collaborator_id' => null,
                    'candidate_ids' => [],
                    'wa_hired_on' => null,
                    'wa_code' => null,
                ];
                continue;
            }

            $collaborator = $hits->first();
            $used[$collaborator->id] = true;
            $rows[] = $this->matched($item, $collaborator);
        }

        foreach ($pool as $collaborator) {
            if (isset($used[$collaborator->id]) || ! $collaborator->active) {
                continue;
            }

            $rows[] = [
                'code' => null,
                'name' => $collaborator->name,
                'admission_on' => null,
                'bucket' => AccountingListRow::BUCKET_ONLY_WA,
                'collaborator_id' => $collaborator->id,
                'candidate_ids' => [$collaborator->id],
                'wa_hired_on' => $collaborator->hiredAt()?->toDateString(),
                'wa_code' => $collaborator->accounting_code,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Collaborator>  $pool
     * @param  array<int, bool>  $used
     * @param  array{code:string,name:string,admission_on:string}  $item
     * @return Collection<int, Collaborator>
     */
    private function candidates(array $item, Collection $pool, array $used): Collection
    {
        $available = $pool->reject(fn (Collaborator $collaborator) => isset($used[$collaborator->id]));

        $byCode = $available->filter(function (Collaborator $collaborator) use ($item) {
            return $this->normalizeCode($collaborator->accounting_code) === $item['code'];
        });
        if ($byCode->isNotEmpty()) {
            return $byCode->values();
        }

        $key = PersonName::key($item['name']);
        $exact = $available->filter(fn (Collaborator $collaborator) => PersonName::key($collaborator->name) === $key);
        if ($exact->isNotEmpty()) {
            return $exact->values();
        }

        return $available
            ->filter(fn (Collaborator $collaborator) => PersonName::tokensFit($item['name'], $collaborator->name))
            ->values();
    }

    /**
     * @param  Collection<int, Collaborator>  $hits
     * @return Collection<int, Collaborator>
     */
    private function breakTie(Collection $hits, string $admissionOn): Collection
    {
        $byAdmission = $hits->filter(function (Collaborator $collaborator) use ($admissionOn) {
            return $collaborator->hiredAt()?->toDateString() === $admissionOn;
        });
        if ($byAdmission->count() === 1) {
            return $byAdmission->values();
        }

        $sorted = $hits->sortByDesc(function (Collaborator $collaborator) {
            $stamp = $collaborator->lastDailyAt();

            return $stamp ? $stamp->timestamp : 0;
        })->values();

        $first = $sorted->first();
        $second = $sorted->get(1);
        $firstStamp = $first?->lastDailyAt();
        $secondStamp = $second?->lastDailyAt();

        if ($first && $firstStamp && (! $secondStamp || $firstStamp->gt($secondStamp))) {
            return collect([$first]);
        }

        return $hits->values();
    }

    /**
     * @param  array{code:string,name:string,admission_on:string}  $item
     * @param  Collection<int, Collaborator>  $hits
     * @return array<string, mixed>
     */
    private function ambiguous(array $item, Collection $hits): array
    {
        return [
            'code' => $item['code'],
            'name' => $item['name'],
            'admission_on' => $item['admission_on'],
            'bucket' => AccountingListRow::BUCKET_AMBIGUOUS,
            'collaborator_id' => null,
            'candidate_ids' => $hits->pluck('id')->all(),
            'wa_hired_on' => null,
            'wa_code' => null,
            'candidates' => $hits->map(fn (Collaborator $collaborator) => [
                'id' => $collaborator->id,
                'name' => $collaborator->name,
                'last_daily' => $collaborator->lastDailyAt()?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array{code:string,name:string,admission_on:string}  $item
     * @return array<string, mixed>
     */
    private function matched(array $item, Collaborator $collaborator): array
    {
        $waHired = $collaborator->hiredAt()?->toDateString();
        $codeOk = $this->normalizeCode($collaborator->accounting_code) === $item['code'];
        $dateOk = $waHired === $item['admission_on'];
        $bucket = ($codeOk && $dateOk)
            ? AccountingListRow::BUCKET_OK
            : AccountingListRow::BUCKET_HIRED_AT;

        return [
            'code' => $item['code'],
            'name' => $item['name'],
            'admission_on' => $item['admission_on'],
            'bucket' => $bucket,
            'collaborator_id' => $collaborator->id,
            'candidate_ids' => [$collaborator->id],
            'wa_hired_on' => $waHired,
            'wa_code' => $collaborator->accounting_code,
        ];
    }

    public function normalizeCode(?string $code): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $code) ?: '';
        if ($digits === '') {
            return null;
        }

        return str_pad(substr($digits, -6), 6, '0', STR_PAD_LEFT);
    }
}
