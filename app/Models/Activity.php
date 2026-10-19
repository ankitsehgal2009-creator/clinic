<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** One line in the activity log: who did what, and when. */
    public static function log(User $user, string $action, ?string $details = null): void
    {
        static::create(['clinic_id' => $user->clinic_id, 'user_id' => $user->id, 'action' => $action, 'details' => $details]);
    }

    /** A line written without a staff member, e.g. a patient booking online. */
    public static function logSystem(int $clinicId, string $action, ?string $details = null): void
    {
        static::create(['clinic_id' => $clinicId, 'user_id' => null, 'action' => $action, 'details' => $details]);
    }
}
