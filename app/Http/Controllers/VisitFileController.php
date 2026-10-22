<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Visit;
use App\Models\VisitFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Prescription photos (up to 5) and X-rays (up to 50) for each visit. The browser shrinks photos before upload. */
class VisitFileController extends Controller
{
    public function store(Request $r, Visit $visit)
    {
        $this->own($visit);
        $v = $r->validate([
            'kind' => 'required|in:RX,XRAY',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:15360',
            'thumb' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'name' => 'nullable|string|max:150',
        ], [
            'file.mimes' => 'Please use a JPG or PNG photo.',
            'file.max' => 'This photo is larger than 15 MB.',
            'file.uploaded' => 'This photo could not be uploaded. It may be too large for the server.',
        ]);
        $me = $this->me();
        $kind = $v['kind'];

        $row = DB::transaction(function () use ($r, $visit, $me, $kind, $v) {
            Visit::whereKey($visit->id)->lockForUpdate()->first();
            $limit = VisitFile::LIMITS[$kind];
            if (VisitFile::where(['visit_id' => $visit->id, 'kind' => $kind])->count() >= $limit) {
                abort(response()->json(['message' => "This visit already has $limit ".($kind === 'RX' ? 'prescription photos' : 'X-rays').', the most allowed.'], 422));
            }
            $dir = "clinic-$visit->clinic_id/visits/$visit->id";
            $base = Str::random(32);
            $file = $r->file('file');
            $path = $file->storeAs($dir, $base.'.'.strtolower($file->extension() ?: 'jpg'), 'local');
            $thumb = $r->hasFile('thumb') ? $r->file('thumb')->storeAs($dir, $base.'-t.'.strtolower($r->file('thumb')->extension() ?: 'jpg'), 'local') : null;
            return VisitFile::create([
                'clinic_id' => $visit->clinic_id, 'visit_id' => $visit->id, 'patient_id' => $visit->patient_id,
                'kind' => $kind, 'path' => $path, 'thumb' => $thumb,
                'name' => $v['name'] ?? $file->getClientOriginalName(), 'size' => $file->getSize(), 'uploaded_by' => $me->id,
            ]);
        });
        $visit->loadMissing('patient');
        Activity::log($me, $kind === 'RX' ? 'Added prescription photo' : 'Added X-ray', $visit->patient->name.' · '.$visit->patient->code.' · visit '.date('j M Y', strtotime($visit->date)));
        return response()->json(self::json($row, $me));
    }

    public function show(Request $r, VisitFile $file)
    {
        $this->own($file);
        $path = $r->boolean('thumb') && $file->thumb ? $file->thumb : $file->path;
        abort_unless(Storage::disk('local')->exists($path), 404);
        $res = Storage::disk('local')->response($path, $r->boolean('download') ? ($file->name ?: basename($path)) : null, [
            'Cache-Control' => 'private, max-age=604800',
        ], $r->boolean('download') ? 'attachment' : 'inline');
        return $res;
    }

    public function destroy(VisitFile $file)
    {
        $this->own($file);
        $me = $this->me();
        abort_unless($me->isAdmin(), 403, 'Only the admin can delete photos permanently.');
        $file->load('uploader');
        $p = \App\Models\Patient::find($file->patient_id);
        $file->purge();
        Activity::log($me, $file->kind === 'RX' ? 'Deleted prescription photo' : 'Deleted X-ray', ($p ? "$p->name · $p->code" : ''));
        return response()->json(['ok' => true]);
    }

    public static function json(VisitFile $f, $me): array
    {
        return [
            'id' => $f->id, 'kind' => $f->kind, 'name' => $f->name,
            'url' => route('visitfiles.show', $f), 'thumb' => route('visitfiles.show', [$f, 'thumb' => 1]),
            'download' => route('visitfiles.show', [$f, 'download' => 1]),
            'del' => $me->isAdmin() ? route('visitfiles.destroy', $f) : null,
            'at' => $f->created_at?->timezone('Asia/Kolkata')->format('j M Y, g:i A'),
            'by' => $f->uploader?->name,
        ];
    }
}
