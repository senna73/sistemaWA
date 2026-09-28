<?php

namespace App\Support;

class Document
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function looksLikeEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function looksLikeCpf(string $value): bool
    {
        return strlen(self::digits($value)) === 11;
    }
}
