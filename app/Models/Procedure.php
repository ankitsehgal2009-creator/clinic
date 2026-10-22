<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One entry in the clinic's "Procedure done" list. */
class Procedure extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
