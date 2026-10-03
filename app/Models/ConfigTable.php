<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigTable extends Model
{
    public const PORTAL_EARNINGS = 'portal_collaborator_earnings_enabled';

    public const PORTAL_DAILY_RATES = 'portal_collaborator_daily_rates_enabled';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'value',
    ];

    protected $table = 'config_table';

    public static function getValue($id)
    {
        return ConfigTable::where('id', $id)->first()?->value;
    }

    public static function enabled(string $id, bool $default = false): bool
    {
        $value = static::getValue($id);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
    }

    public static function put(string $id, string $value): void
    {
        static::query()->updateOrCreate(
            ['id' => $id],
            ['value' => $value]
        );
    }

    public static function putBool(string $id, bool $enabled): void
    {
        static::put($id, $enabled ? '1' : '0');
    }
}
