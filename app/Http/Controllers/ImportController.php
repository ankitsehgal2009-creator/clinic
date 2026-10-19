<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Patient;
use App\Support\Fmt;
use App\Support\SheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportController extends Controller
{
    public function show()
    {
        return view('patients.import');
    }

    /** A sample CSV with every column SmileDesk can read, so people can see the format. Opens in Excel. */
    public function sample()
    {
        $rows = [
            ['Old patient ID', 'Full name', 'Mobile number', 'Gender', 'Date of birth', 'Age', 'Father / husband name', 'Address', 'Medical history'],
            ['1021', 'Rohan Deshpande', '9820145567', 'Male', '12/03/1992', '', 'Suresh Deshpande', 'Andheri West, Mumbai', 'Diabetes; BP'],
            ['1022', 'Meera Iyer', '+91 99300 21456', 'Female', '', '29', 'Karthik Iyer', 'Powai, Mumbai', ''],
            ['1023', 'Aditya Joshi', '09892560031', 'M', '14-12-2016', '', 'Nikhil Joshi', '', 'Allergic to penicillin'],
        ];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel shows ₹ and Hindi names correctly
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);
        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="smiledesk-sample-patients.csv"',
        ]);
    }

    /** Reads an uploaded CSV or .xlsx and sends its rows back so the browser can map columns. */
    public function read(Request $r)
    {
        $r->validate(['file' => 'required|file|max:20480'], ['file.max' => 'That file is over 20 MB. Split it into smaller files.']);
        $f = $r->file('file');
        try {
            $rows = SheetReader::read($f->getRealPath(), strtolower($f->getClientOriginalExtension()));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        if (count($rows) < 2) {
            return response()->json(['error' => 'That file has no patient rows. It needs a header row and at least one patient.'], 422);
        }
        $headers = array_shift($rows);
        $width = count($headers);
        $rows = array_map(fn ($row) => array_slice(array_pad($row, $width, ''), 0, $width), array_slice($rows, 0, 20000));
        return response()->json(['name' => $f->getClientOriginalName(), 'headers' => $headers, 'rows' => $rows]);
    }

    /** Imports rows already mapped by the browser. Rows with a bad mobile or a same name+mobile match are skipped. */
    public function store(Request $r)
    {
        $me = $this->me();
        $v = $r->validate([
            'file' => 'required|string|max:255',
            'rows' => 'required|array|max:20000',
            'rows.*.name' => 'required|string|max:150',
            'rows.*.mobile' => 'required|string|max:30',
            'rows.*.*' => 'nullable|string|max:500',
        ]);
        $seen = [];
        foreach (Patient::where('clinic_id', $me->clinic_id)->get(['name', 'mobile']) as $e) {
            $seen[mb_strtolower($e->name).'|'.$e->mobile] = true;
        }
        $added = $skipped = 0;
        foreach (array_chunk($v['rows'], 200) as $chunk) {
            DB::transaction(function () use ($chunk, $me, &$seen, &$added, &$skipped) {
                foreach ($chunk as $row) {
                    $name = trim($row['name']);
                    $mobile = Fmt::mobile($row['mobile']);
                    $key = mb_strtolower($name).'|'.$mobile;
                    if (! $mobile || $name === '' || isset($seen[$key])) {
                        $skipped++;
                        continue;
                    }
                    $seen[$key] = true;
                    $dob = self::parseDob($row['dob'] ?? null);
                    $g = strtolower($row['gender'] ?? '');
                    $notes = trim($row['notes'] ?? '');
                    Patient::create([
                        'clinic_id' => $me->clinic_id,
                        'code' => PatientController::nextCode($me->clinic_id),
                        'name' => mb_substr($name, 0, 150),
                        'mobile' => $mobile,
                        'gender' => str_starts_with($g, 'f') ? 'Female' : (str_starts_with($g, 'm') ? 'Male' : null),
                        'dob' => $dob,
                        'age' => $dob ? Fmt::ageFrom($dob) : (is_numeric($row['age'] ?? null) && $row['age'] >= 0 && $row['age'] <= 120 ? (int) $row['age'] : null),
                        'relative_name' => ($row['relative_name'] ?? '') !== '' ? mb_substr($row['relative_name'], 0, 150) : null,
                        'address' => ($row['address'] ?? '') !== '' ? mb_substr($row['address'], 0, 250) : null,
                        'old_id' => ($row['old_id'] ?? '') !== '' ? mb_substr($row['old_id'], 0, 50) : null,
                        'medical_history' => $notes === '' ? [] : array_values(array_filter(array_map('trim', preg_split('/[;,]/', $notes)))),
                    ]);
                    $added++;
                }
            });
        }
        Activity::log($me, 'Imported old patients', "{$v['file']} · $added added, $skipped skipped");
        return response()->json(['added' => $added, 'skipped' => $skipped]);
    }

    /** Accepts DD/MM/YYYY (Indian), YYYY-MM-DD or DD-MM-YYYY. */
    private static function parseDob(?string $s): ?string
    {
        $s = trim((string) $s);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
            [$y, $mo, $d] = [$m[1], $m[2], $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $s, $m)) {
            [$d, $mo, $y] = [$m[1], $m[2], $m[3]];
        } else {
            return null;
        }
        if (! checkdate((int) $mo, (int) $d, (int) $y) || "$y" > date('Y')) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
}
