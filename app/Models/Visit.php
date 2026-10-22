<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One visit: notes the doctor writes, and the prescription printed from it. */
class Visit extends Model
{
    protected $guarded = ['id'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function medicines()
    {
        return $this->hasMany(VisitMedicine::class)->orderBy('id');
    }

    public function files()
    {
        return $this->hasMany(VisitFile::class)->orderBy('id');
    }
}
