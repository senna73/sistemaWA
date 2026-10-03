<?php

namespace App\Services\Rh;

use App\Models\AccountingListCheck;
use App\Models\AccountingListRow;
use App\Models\Collaborator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingListService
{
    public function __construct(
        private AccountingListParser $parser,
        private AccountingReconciler $reconciler,
    ) {}

    public function ingest(User $actor, UploadedFile $file): AccountingListCheck
    {
        $parsed = $this->parser->parse($file->getRealPath() ?: $file->getPathname(), $file->getClientOriginalName());
        $compared = $this->reconciler->compare($parsed);
        $path = $file->store('accounting-lists', 'local');

        return DB::transaction(function () use ($actor, $file, $path, $parsed, $compared) {
            $check = AccountingListCheck::query()->create([
                'uploaded_by' => $actor->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'source' => strtolower((string) $file->getClientOriginalExtension()) ?: 'csv',
                'row_count' => count($parsed),
                'status' => AccountingListCheck::STATUS_OPEN,
            ]);

            foreach ($compared as $row) {
                AccountingListRow::query()->create([
                    'accounting_list_check_id' => $check->id,
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'],
                    'admission_on' => $row['admission_on'] ?? null,
                    'bucket' => $row['bucket'],
                    'collaborator_id' => $row['collaborator_id'] ?? null,
                    'candidate_ids' => $row['candidate_ids'] ?? [],
                    'wa_hired_on' => $row['wa_hired_on'] ?? null,
                    'wa_code' => $row['wa_code'] ?? null,
                    'snapshot' => [
                        'candidates' => $row['candidates'] ?? [],
                    ],
                    'resolved_at' => ($row['bucket'] ?? null) === AccountingListRow::BUCKET_OK ? now() : null,
                    'resolution' => ($row['bucket'] ?? null) === AccountingListRow::BUCKET_OK ? 'ok' : null,
                ]);
            }

            return $check->fresh('rows');
        });
    }

    public function current(): ?AccountingListCheck
    {
        return AccountingListCheck::query()->latest('id')->first();
    }

    public function apply(AccountingListRow $row, User $actor, array $payload = []): AccountingListRow
    {
        if ($row->isResolved()) {
            throw ValidationException::withMessages(['row' => 'Este item já foi conferido.']);
        }

        return DB::transaction(function () use ($row, $actor, $payload) {
            return match ($row->bucket) {
                AccountingListRow::BUCKET_HIRED_AT => $this->applyHiredAt($row, $actor),
                AccountingListRow::BUCKET_ONLY_WA => $this->inactivate($row, $actor),
                AccountingListRow::BUCKET_AMBIGUOUS => $this->pick($row, $actor, (int) ($payload['collaborator_id'] ?? 0)),
                AccountingListRow::BUCKET_ONLY_LIST => throw ValidationException::withMessages([
                    'row' => 'Cadastre o colaborador com os dados da lista e volte para conferir.',
                ]),
                default => $row,
            };
        });
    }

    public function markCreatedFromRow(int $rowId, Collaborator $collaborator, ?User $actor = null): void
    {
        $row = AccountingListRow::query()->find($rowId);
        if (! $row || $row->isResolved()) {
            return;
        }

        $before = $this->snapshot($collaborator);
        $row->update([
            'collaborator_id' => $collaborator->id,
            'resolved_at' => now(),
            'resolved_by' => $actor?->id,
            'resolution' => 'created',
            'snapshot' => ['before' => $before, 'after' => $this->snapshot($collaborator->fresh())],
        ]);
    }

    private function applyHiredAt(AccountingListRow $row, User $actor): AccountingListRow
    {
        $collaborator = $row->collaborator;
        if (! $collaborator) {
            throw ValidationException::withMessages(['row' => 'Sem cadastro WA para atualizar a admissão.']);
        }

        $before = $this->snapshot($collaborator);
        $collaborator->update([
            'hired_at' => $row->admission_on?->startOfDay(),
            'accounting_code' => $row->code ?: $collaborator->accounting_code,
        ]);

        return $this->resolve($row, $actor, 'hired_at', $before, $this->snapshot($collaborator->fresh()));
    }

    private function inactivate(AccountingListRow $row, User $actor): AccountingListRow
    {
        $collaborator = $row->collaborator;
        if (! $collaborator) {
            throw ValidationException::withMessages(['row' => 'Sem cadastro WA para inativar.']);
        }

        $before = $this->snapshot($collaborator);
        $note = trim((string) $collaborator->observation);
        $mark = 'Fora da lista da contabilidade';
        $collaborator->update([
            'active' => false,
            'observation' => $note === '' ? $mark : $note."\n".$mark,
        ]);

        return $this->resolve($row, $actor, 'inactivate', $before, $this->snapshot($collaborator->fresh()));
    }

    private function pick(AccountingListRow $row, User $actor, int $collaboratorId): AccountingListRow
    {
        $ids = collect($row->candidate_ids ?? []);
        if (! $ids->contains($collaboratorId)) {
            throw ValidationException::withMessages(['collaborator_id' => 'Escolha um dos cadastros sugeridos.']);
        }

        $keep = Collaborator::query()->findOrFail($collaboratorId);
        $before = $this->snapshot($keep);
        $keep->update([
            'hired_at' => $row->admission_on?->startOfDay() ?? $keep->hired_at,
            'accounting_code' => $row->code ?: $keep->accounting_code,
            'active' => true,
        ]);

        Collaborator::query()
            ->whereIn('id', $ids->all())
            ->where('id', '!=', $keep->id)
            ->get()
            ->each(function (Collaborator $other) {
                $note = trim((string) $other->observation);
                $mark = 'Duplicata da lista da contabilidade — ficou o cadastro com diária mais recente';
                $other->update([
                    'active' => false,
                    'observation' => $note === '' ? $mark : $note."\n".$mark,
                ]);
            });

        $row->collaborator_id = $keep->id;

        return $this->resolve($row, $actor, 'pick', $before, $this->snapshot($keep->fresh()));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function resolve(AccountingListRow $row, User $actor, string $resolution, array $before, array $after): AccountingListRow
    {
        $row->fill([
            'resolved_at' => now(),
            'resolved_by' => $actor->id,
            'resolution' => $resolution,
            'snapshot' => ['before' => $before, 'after' => $after],
        ])->save();

        return $row->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Collaborator $collaborator): array
    {
        return [
            'id' => $collaborator->id,
            'name' => $collaborator->name,
            'active' => (bool) $collaborator->active,
            'hired_at' => $collaborator->hiredAt()?->toDateString(),
            'accounting_code' => $collaborator->accounting_code,
        ];
    }
}
