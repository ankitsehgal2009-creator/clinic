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
}
