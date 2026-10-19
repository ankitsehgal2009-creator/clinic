<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['notes_count' => 'array'];
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
