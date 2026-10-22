<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['medical_history' => 'array', 'active' => 'boolean'];
    }

    /** Age today: worked out from the date of birth when there is one, so it never goes stale. */
    protected function age(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(fn ($v, $a) => ! empty($a['dob']) ? \App\Support\Fmt::ageFrom($a['dob']) : ($v === null ? null : (int) $v));
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /** Unpaid balance across all bills that aren't cancelled, in paise. */
    public function balance(): int
    {
        return (int) $this->invoices()->whereNull('cancelled_at')->sum(\Illuminate\Support\Facades\DB::raw('total - paid'));
    }

    public function relativeLabel(): ?string
    {
        if (! $this->relative_name) {
            return null;
        }
        $rel = \App\Support\Status::REL[$this->relation_type] ?? null;
        return ($rel ? "$rel: " : '').$this->relative_name;
    }
}
