<?php

namespace App\Support;

use Illuminate\Support\Str;

class PersonName
{
    /** @var list<string> */
    private const STOP = ['da', 'de', 'do', 'das', 'dos', 'e', 'di', 'du', 'del', 'la'];

    public static function key(string $name): string
    {
        $ascii = Str::of($name)->ascii()->lower()->toString();
        $ascii = preg_replace('/[^a-z0-9\s]/', ' ', $ascii) ?? '';
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? '';

        return trim($ascii);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $name): array
    {
        $parts = explode(' ', self::key($name));

        return array_values(array_filter(
            $parts,
            fn (string $token) => $token !== '' && ! in_array($token, self::STOP, true)
        ));
    }

    public static function tokensFit(string $shorter, string $longer): bool
    {
        $a = self::tokens($shorter);
        $b = self::tokens($longer);

        if (count($a) < 2 || count($b) < 2) {
            return false;
        }

        $haystack = count($a) <= count($b) ? $b : $a;
        $needle = count($a) <= count($b) ? $a : $b;

        foreach ($needle as $token) {
            if (! in_array($token, $haystack, true)) {
                return false;
            }
        }

        return true;
    }
}
