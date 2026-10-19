<?php

namespace App\Support;

/** FDI tooth numbering used by Indian dental colleges, and the states a tooth can be marked with. */
class Teeth
{
    public const UPPER = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
    public const LOWER = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
    public const UPPER_CHILD = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
    public const LOWER_CHILD = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

    public const STATES = [
        'healthy' => 'Healthy',
        'caries' => 'Caries (decay)',
        'filled' => 'Filled',
        'rct' => 'Root canal',
        'crown' => 'Crown',
        'implant' => 'Implant',
        'missing' => 'Missing',
        'planned' => 'Treatment planned',
    ];

    public static function valid(int $n): bool
    {
        return in_array($n, [...self::UPPER, ...self::LOWER, ...self::UPPER_CHILD, ...self::LOWER_CHILD], true);
    }

    /** "Upper right first molar" style name, so staff don't need to know the numbers. */
    public static function name(int $n): string
    {
        $q = intdiv($n, 10);
        $i = $n % 10;
        $child = $q >= 5;
        $side = [1 => 'Upper right', 2 => 'Upper left', 3 => 'Lower left', 4 => 'Lower right', 5 => 'Upper right', 6 => 'Upper left', 7 => 'Lower left', 8 => 'Lower right'][$q] ?? '';
        $kind = $child
            ? [1 => 'central incisor', 2 => 'lateral incisor', 3 => 'canine', 4 => 'first molar', 5 => 'second molar'][$i]
            : [1 => 'central incisor', 2 => 'lateral incisor', 3 => 'canine', 4 => 'first premolar', 5 => 'second premolar', 6 => 'first molar', 7 => 'second molar', 8 => 'third molar (wisdom tooth)'][$i];
        return "$side $kind".($child ? ' (milk tooth)' : '');
    }

    /** Numbers mentioned in a visit's teeth field, like "36, 37" or "45-47". */
    public static function parse(?string $s): array
    {
        preg_match_all('/\b([1-8][1-8])\b/', (string) $s, $m);
        return array_values(array_unique(array_map('intval', array_filter($m[1], fn ($n) => self::valid((int) $n)))));
    }
}
