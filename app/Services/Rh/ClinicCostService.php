<?php

namespace App\Services\Rh;

use App\Models\ClinicPrice;
use App\Models\OffboardingProcess;
use App\Models\RhCostEntry;

class ClinicCostService
{
    public const MONTHLY_CLIOMED = 8.50;

    public function priceFor(string $category, ?string $city = null): float
    {
        $query = ClinicPrice::query()->where('category', $category)->orderByDesc('id');
        if ($city) {
            $query->where('city', $city);
        }
        $row = $query->first();

        return $row ? (float) $row->amount : 0.0;
    }

    public function ensureExamCost(OffboardingProcess $process): RhCostEntry
    {
        return $this->ensure($process, RhCostEntry::CATEGORY_EXAM);
    }

    public function ensureMissedExamCost(OffboardingProcess $process): RhCostEntry
    {
        return $this->ensure($process, RhCostEntry::CATEGORY_MISS);
    }

    public function ensureMailCost(OffboardingProcess $process, float $amount = 0): RhCostEntry
    {
        return $this->ensure($process, RhCostEntry::CATEGORY_MAIL, $amount);
    }

    private function ensure(OffboardingProcess $process, string $category, ?float $amount = null): RhCostEntry
    {
        $city = $process->collaborator?->city;
        $expected = $amount ?? $this->priceFor($category, $city);

        return RhCostEntry::query()->firstOrCreate(
            [
                'offboarding_process_id' => $process->id,
                'category' => $category,
            ],
            [
                'collaborator_id' => $process->collaborator_id,
                'city' => $city,
                'expected_amount' => $expected,
                'status' => 'open',
            ]
        );
    }
}
