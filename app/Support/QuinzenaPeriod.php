<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class QuinzenaPeriod
{
    public static function currentQuinzena(?CarbonInterface $now = null): int
    {
        $now ??= now();

        return $now->day <= 15 ? 1 : 2;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $yearMonth, int $quinzena): array
    {
        $month = Carbon::createFromFormat('!Y-m', $yearMonth) ?: now()->startOfMonth();
        $month = $month->copy()->startOfMonth();

        if ($quinzena === 1) {
            return [
                $month->copy()->startOfDay(),
                $month->copy()->day(15)->endOfDay(),
            ];
        }

        return [
            $month->copy()->day(16)->startOfDay(),
            $month->copy()->endOfMonth()->endOfDay(),
        ];
    }
}
