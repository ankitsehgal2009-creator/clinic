<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    protected $guarded = ['id'];

    /** Start minute of every bookable slot, morning then evening. */
    public function slots(): array
    {
        $out = [];
        foreach ([[$this->morning_open, $this->morning_close], [$this->evening_open, $this->evening_close]] as [$a, $b]) {
            for ($m = $a; $m < $b; $m += $this->slot_minutes) {
                $out[] = $m;
            }
        }
        return $out;
    }

    public function chairs()
    {
        return $this->hasMany(Chair::class);
    }
}
