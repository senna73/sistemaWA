<?php

namespace App\Services\Rh;

use App\Models\Collaborator;
use App\Models\DailyRate;
use App\Models\User;
use Illuminate\Support\Collection;

class RelatedStaff
{
    /**
     * Coordenadores ligados às diárias e às lojas em que o colaborador trabalhou.
     *
     * @return Collection<int, User>
     */
    public function coordinatorsFor(Collaborator $collaborator): Collection
    {
        $fromRates = DailyRate::query()
            ->where('collaborator_id', $collaborator->id)
            ->whereNotNull('coordinator_id')
            ->pluck('coordinator_id');

        $fromCompanies = DailyRate::query()
            ->where('collaborator_id', $collaborator->id)
            ->whereNotNull('company_id')
            ->with('company:id,coordinator_id')
            ->get()
            ->pluck('company.coordinator_id')
            ->filter();

        $ids = $fromRates->merge($fromCompanies)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->where('active', true)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function rhUsers(): Collection
    {
        return User::query()->where('role', 'rh')->where('active', true)->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function owners(): Collection
    {
        return User::query()->where('role', 'super_admin')->where('active', true)->get();
    }

    /**
     * Destinatários de alerta (RH + donos + coordenadores). Sem o colaborador.
     *
     * @return Collection<int, User>
     */
    public function staffRecipients(Collaborator $collaborator): Collection
    {
        return $this->rhUsers()
            ->concat($this->owners())
            ->concat($this->coordinatorsFor($collaborator))
            ->unique('id')
            ->values();
    }
}
