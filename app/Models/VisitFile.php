<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A photo of a handwritten prescription or an X-ray, attached to a visit. */
class VisitFile extends Model
{
    public const KINDS = ['RX' => 'Prescription photos', 'XRAY' => 'X-rays'];
    public const LIMITS = ['RX' => 5, 'XRAY' => 50];

    protected $guarded = ['id'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Removes the stored files too. */
    public function purge(): void
    {
        Storage::disk('local')->delete(array_filter([$this->path, $this->thumb]));
        $this->delete();
    }
}
