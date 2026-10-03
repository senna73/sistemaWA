<?php

use App\Models\ConfigTable;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ConfigTable::putBool(ConfigTable::PORTAL_EARNINGS, false);
        ConfigTable::putBool(ConfigTable::PORTAL_DAILY_RATES, false);
    }

    public function down(): void
    {
        ConfigTable::query()->whereIn('id', [
            ConfigTable::PORTAL_EARNINGS,
            ConfigTable::PORTAL_DAILY_RATES,
        ])->delete();
    }
};
