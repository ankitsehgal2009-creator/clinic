<?php

namespace App\Support;

/** Dates are YYYY-MM-DD strings in India time (the app timezone) so a day never shifts. */
class Fmt
{
    public static function today(): string
    {
        return date('Y-m-d');
    }

    public static function nowMinutes(): int
    {
        return (int) date('G') * 60 + (int) date('i');
    }

    public static function time(int $min): string
    {
        $h = intdiv($min, 60);
        $ap = $h >= 12 ? 'PM' : 'AM';
        $h = $h % 12 ?: 12;
        return sprintf('%d:%02d %s', $h, $min % 60, $ap);
    }

    public static function date(string $key): string
    {
        return date('D, j M Y', strtotime($key));
    }

    public static function isDate($s): bool
    {
        return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4));
    }

    public static function addDays(string $key, int $n): string
    {
        return date('Y-m-d', strtotime("$key $n day"));
    }

    /** Normalise an Indian mobile number to 10 digits, or return null. */
    public static function mobile(?string $v): ?string
    {
        $d = preg_replace('/\D/', '', (string) $v);
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        return preg_match('/^[6-9]\d{9}$/', $d) ? $d : null;
    }

    public static function showMobile(string $d): string
    {
        return strlen($d) === 10 ? substr($d, 0, 5).' '.substr($d, 5) : $d;
    }

    public static function ageFrom(string $dob): int
    {
        return (int) date_diff(date_create($dob), date_create('today'))->y;
    }

    public static function initials(string $name): string
    {
        $words = array_filter(preg_split('/\s+/', preg_replace('/^Dr\.\s*/', '', trim($name))));
        return strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));
    }
}
