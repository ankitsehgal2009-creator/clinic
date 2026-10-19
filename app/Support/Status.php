<?php

namespace App\Support;

class Status
{
    public const STATUS = [
        'BOOKED' => ['label' => 'Booked', 'cls' => 'c-pri'],
        'WAITING' => ['label' => 'Waiting', 'cls' => 'c-warn'],
        'IN_CHAIR' => ['label' => 'In chair', 'cls' => 'c-info'],
        'COMPLETED' => ['label' => 'Completed', 'cls' => 'c-ok'],
        'NO_SHOW' => ['label' => 'No-show', 'cls' => 'c-bad'],
        'CANCELLED' => ['label' => 'Cancelled', 'cls' => 'c-bad'],
    ];

    public const REL = ['FATHER' => 'Father', 'HUSBAND' => 'Husband', 'WIFE' => 'Wife', 'MOTHER' => 'Mother'];

    public const TREATMENTS = ['Consultation', 'Filling', 'RCT sitting', 'Scaling', 'Extraction', 'Crown', 'Braces adjustment', 'X-ray', 'Follow-up'];

    public static function label(string $s): string
    {
        return self::STATUS[$s]['label'] ?? $s;
    }

    public static function cls(string $s): string
    {
        return self::STATUS[$s]['cls'] ?? '';
    }
}
