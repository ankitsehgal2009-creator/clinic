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

    /** Username rule: 3 to 30 letters, digits, dots or underscores. */
    protected static function usernameRule(): \Closure
    {
        return function ($attr, $value, $fail) {
            if (! User::cleanUsername($value)) {
                $fail('Usernames need 3 to 30 letters or numbers, with no spaces (a dot or _ is fine), like priya or dr.mehta.');
            }
        };
    }

    /** A small picture sent from the browser as a PNG, JPEG or WebP data URL. */
    protected static function imageRule(): \Closure
    {
        return function ($attr, $value, $fail) {
            if ($value && ! preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#', $value)) {
                $fail('That picture could not be read. Try a PNG or JPG file.');
            }
        };
    }
}
