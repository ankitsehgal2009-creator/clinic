<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentPrice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** A new clinic starts with the usual dental price list, which it can change. */
    public static function seedDefaults(int $clinicId): void
    {
        foreach (\App\Support\Money::DEFAULT_PRICES as [$name, $rupees, $gst]) {
            static::create(['clinic_id' => $clinicId, 'name' => $name, 'price' => $rupees * 100, 'gst_rate' => $gst]);
        }
    }

    /** Invoice number prefix from the patient ID prefix: "SC-" gives "SC". */
    public static function prefixFrom(string $patientPrefix): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $patientPrefix)) ?: 'INV';
    }
}
