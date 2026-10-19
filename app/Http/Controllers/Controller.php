<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    protected function me(): User
    {
        return request()->user();
    }

    /** 404 unless the record belongs to the signed-in user's clinic. */
    protected function own(Model $m): Model
    {
        abort_unless((int) $m->clinic_id === (int) $this->me()->clinic_id, 404);
        return $m;
    }

    /** 10-digit mobile rule with a readable message. */
    protected static function mobileRule(string $label = 'mobile number'): \Closure
    {
        return function ($attr, $value, $fail) use ($label) {
            if (! \App\Support\Fmt::mobile($value)) {
                $fail("\"$value\" is not a valid 10-digit $label.");
            }
        };
    }
}
