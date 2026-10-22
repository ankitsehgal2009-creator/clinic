<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One medicine in the clinic's library, with the dose it is usually written with. */
class Medicine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
