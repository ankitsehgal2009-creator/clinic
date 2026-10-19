<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RxTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meds' => 'array', 'active' => 'boolean'];
    }
}
