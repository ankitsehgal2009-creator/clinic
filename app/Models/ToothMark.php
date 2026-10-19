<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToothMark extends Model
{
    protected $guarded = ['id'];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
