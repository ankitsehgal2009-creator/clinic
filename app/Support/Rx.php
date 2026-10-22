<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Prescription designs, medicine forms and the starter templates every clinic gets. */
class Rx
{
    public const DESIGNS = [
        'classic' => ['Classic', 'Letterhead with a rule. The usual clinic pad.'],
        'modern' => ['Modern', 'Colour band at the top, patient details in boxes.'],
        'minimal' => ['Minimal', 'Lots of white space. Prints well in black and white.'],
        'bilingual' => ['Bilingual', 'Hindi and English labels, double border, stamp box.'],
    ];

    public const FORMS = ['Tablet', 'Capsule', 'Syrup', 'Mouthwash', 'Gel', 'Paste', 'Drops', 'Injection', 'Ointment', 'Other'];

    public const TIMINGS = ['After food', 'Before food', 'Before breakfast', 'At bedtime', 'After brushing', 'SOS (when needed)'];

    public const TEMPLATES = [
        ['Post RCT', [['Amoxicillin 500 mg', 'Capsule', '1-1-1', 'After food', '5 days'], ['Ibuprofen 400 mg + Paracetamol 325 mg', 'Tablet', '1-0-1', 'After food', '3 days'], ['Chlorhexidine 0.2%', 'Mouthwash', '10 ml twice', 'After brushing', '7 days']], 'Avoid chewing hard food on the treated side until the crown is fitted.'],
        ['Post extraction', [['Amoxicillin + Clavulanic acid 625 mg', 'Tablet', '1-0-1', 'After food', '5 days'], ['Aceclofenac 100 mg + Paracetamol 325 mg', 'Tablet', '1-0-1', 'After food', '3 days'], ['Pantoprazole 40 mg', 'Tablet', '1-0-0', 'Before breakfast', '5 days']], 'Bite on the gauze for 45 minutes. Cold compress today. No spitting, straw or hot food for 24 hours.'],
        ['Sensitivity', [['Potassium nitrate 5% toothpaste', 'Paste', 'Twice daily', 'After brushing', '30 days']], 'Use a soft brush. Avoid very cold drinks for two weeks.'],
        ['Gum infection', [['Metronidazole 400 mg', 'Tablet', '1-1-1', 'After food', '5 days'], ['Chlorhexidine 0.2%', 'Mouthwash', '10 ml twice', 'After brushing', '10 days']], 'No alcohol while taking metronidazole. Warm salt-water rinses three times a day.'],
        ['Child pain relief', [['Paracetamol syrup 250 mg/5 ml', 'Syrup', '5 ml SOS', 'After food', '3 days']], 'Maximum four doses in a day. Soft food only.'],
    ];

    /** Short prefix printed before a medicine: Tab., Cap., Syp. */
    public static function prefix(?string $form): string
    {
        return ['Tablet' => 'Tab. ', 'Capsule' => 'Cap. ', 'Syrup' => 'Syp. ', 'Injection' => 'Inj. ', 'Ointment' => 'Oint. '][$form] ?? '';
    }

    /** Starter list for "Procedure done". Each clinic can add more or turn these off. */
    public const PROCEDURES = [
        'Consultation', 'Oral prophylaxis (scaling)', 'Composite filling', 'GIC filling', 'Root canal treatment (RCT)',
        'RCT sitting: access opening', 'RCT sitting: cleaning and shaping', 'RCT sitting: obturation', 'Pulpectomy',
        'Extraction', 'Surgical extraction', 'Wisdom tooth removal', 'Crown preparation', 'Crown cementation',
        'Bridge', 'Denture', 'Implant', 'Teeth whitening', 'Braces adjustment', 'IOPA X-ray', 'Fluoride application', 'Follow-up',
    ];

    public static function seedProcedures(int $clinicId): void
    {
        $now = now();
        DB::table('procedures')->insert(array_map(fn ($n) => ['clinic_id' => $clinicId, 'name' => $n, 'active' => true, 'created_at' => $now, 'updated_at' => $now], self::PROCEDURES));
    }

    /** Adds the starter templates to a clinic and makes Post RCT the default. */
    public static function seedTemplates(int $clinicId): void
    {
        $now = now();
        $first = null;
        foreach (self::TEMPLATES as [$name, $meds, $advice]) {
            $id = DB::table('rx_templates')->insertGetId([
                'clinic_id' => $clinicId, 'name' => $name, 'advice' => $advice, 'active' => true, 'uses' => 0,
                'meds' => json_encode(array_map(fn ($m) => array_combine(['name', 'form', 'dose', 'timing', 'days'], $m), $meds)),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $first ??= $id;
        }
        DB::table('clinics')->where('id', $clinicId)->update(['rx_template_id' => $first]);
    }

    /** Starter medicine library: name, form, dose, when, days. Each clinic edits it and it learns new names from visits. */
    public const MEDICINES = [
        ['Amoxicillin 500 mg', 'Capsule', '1-1-1', 'After food', '5 days'],
        ['Amoxicillin + Clavulanic acid 625 mg', 'Tablet', '1-0-1', 'After food', '5 days'],
        ['Metronidazole 400 mg', 'Tablet', '1-1-1', 'After food', '5 days'],
        ['Ornidazole 500 mg', 'Tablet', '1-0-1', 'After food', '5 days'],
        ['Azithromycin 500 mg', 'Tablet', '1-0-0', 'Before food', '3 days'],
        ['Clindamycin 300 mg', 'Capsule', '1-1-1', 'After food', '5 days'],
        ['Cefixime 200 mg', 'Tablet', '1-0-1', 'After food', '5 days'],
        ['Doxycycline 100 mg', 'Capsule', '1-0-1', 'After food', '7 days'],
        ['Ibuprofen 400 mg + Paracetamol 325 mg', 'Tablet', '1-0-1', 'After food', '3 days'],
        ['Aceclofenac 100 mg + Paracetamol 325 mg', 'Tablet', '1-0-1', 'After food', '3 days'],
        ['Aceclofenac 100 mg + Paracetamol 325 mg + Serratiopeptidase 15 mg', 'Tablet', '1-0-1', 'After food', '5 days'],
        ['Diclofenac 50 mg', 'Tablet', '1-0-1', 'After food', '3 days'],
        ['Ketorolac 10 mg', 'Tablet', 'SOS', 'After food', '2 days'],
        ['Paracetamol 650 mg', 'Tablet', '1-1-1', 'After food', '3 days'],
        ['Paracetamol syrup 250 mg/5 ml', 'Syrup', '5 ml SOS', 'After food', '3 days'],
        ['Ibuprofen syrup 100 mg/5 ml', 'Syrup', '5 ml SOS', 'After food', '3 days'],
        ['Amoxicillin syrup 250 mg/5 ml', 'Syrup', '5 ml thrice', 'After food', '5 days'],
        ['Pantoprazole 40 mg', 'Tablet', '1-0-0', 'Before breakfast', '5 days'],
        ['Rabeprazole 20 mg', 'Tablet', '1-0-0', 'Before breakfast', '5 days'],
        ['Chlorhexidine 0.2%', 'Mouthwash', '10 ml twice', 'After brushing', '7 days'],
        ['Povidone iodine 2% gargle', 'Mouthwash', '10 ml thrice', 'After food', '7 days'],
        ['Benzydamine 0.15%', 'Mouthwash', '15 ml thrice', 'After food', '7 days'],
        ['Lignocaine 2% gel', 'Gel', 'Apply thrice', 'Before food', '5 days'],
        ['Choline salicylate + Lignocaine gel', 'Gel', 'Apply thrice', 'After food', '5 days'],
        ['Triamcinolone 0.1% oral paste', 'Paste', 'Apply twice', 'At bedtime', '5 days'],
        ['Metronidazole + Chlorhexidine gel', 'Gel', 'Apply twice', 'After brushing', '7 days'],
        ['Potassium nitrate 5% toothpaste', 'Paste', 'Twice daily', 'After brushing', '30 days'],
        ['Clotrimazole 1% mouth paint', 'Gel', 'Apply thrice', 'After food', '7 days'],
        ['Vitamin B-complex', 'Capsule', '1-0-0', 'After food', '15 days'],
        ['Tranexamic acid 500 mg', 'Tablet', '1-1-1', 'After food', '2 days'],
    ];

    public static function seedMedicines(int $clinicId): void
    {
        $now = now();
        DB::table('medicines')->insertOrIgnore(array_map(fn ($m) => ['clinic_id' => $clinicId, 'name' => $m[0], 'form' => $m[1], 'dose' => $m[2], 'timing' => $m[3], 'days' => $m[4], 'active' => true, 'created_at' => $now, 'updated_at' => $now], self::MEDICINES));
    }

    /** Adds medicine names the library does not know yet (with the dose just written) and counts each use. */
    public static function learnMedicines(int $clinicId, array $meds, bool $count = false): void
    {
        $now = now();
        $rows = [];
        foreach ($meds as $m) {
            $name = trim((string) ($m['name'] ?? ''));
            if ($name === '' || isset($rows[mb_strtolower($name)])) continue;
            $rows[mb_strtolower($name)] = ['clinic_id' => $clinicId, 'name' => mb_substr($name, 0, 150), 'form' => $m['form'] ?? null ?: null, 'dose' => $m['dose'] ?? null ?: null, 'timing' => $m['timing'] ?? null ?: null, 'days' => $m['days'] ?? null ?: null, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        if (! $rows) return;
        DB::table('medicines')->insertOrIgnore(array_values($rows));
        if ($count) {
            DB::table('medicines')->where('clinic_id', $clinicId)->whereIn('name', array_column($rows, 'name'))->increment('uses');
        }
    }

    /** RxTerms answer → [{name: "Amoxicillin 500 mg", form: "Capsule"}], at most 15. */
    public static function parseRxTerms(array $j): array
    {
        $names = $j[1] ?? [];
        $forms = $j[2]['STRENGTHS_AND_FORMS'] ?? [];
        $kind = ['tab' => 'Tablet', 'cap' => 'Capsule', 'susp' => 'Syrup', 'syrup' => 'Syrup', 'soln' => 'Syrup', 'gel' => 'Gel', 'cream' => 'Ointment', 'oint' => 'Ointment', 'paste' => 'Paste', 'mouthwash' => 'Mouthwash', 'rinse' => 'Mouthwash', 'inj' => 'Injection', 'drops' => 'Drops'];
        $out = [];
        foreach ($names as $i => $full) {
            if (! is_string($full)) continue;
            $base = trim(preg_replace('/\s*\(.*\)\s*$/', '', $full));
            $list = is_array($forms[$i] ?? null) ? $forms[$i] : [];
            if (! $list) $out[] = ['name' => $base, 'form' => null];
            foreach ($list as $sf) {
                $sf = trim((string) $sf);
                $form = null;
                foreach ($kind as $k => $v) {
                    if (preg_match('/\b'.$k.'\b/i', $sf)) { $form = $v; break; }
                }
                // "500 mg Cap" → "500 mg"; "0.12 % Mouthwash" → "0.12%"
                $strength = trim(preg_replace('/\s+(tab|cap|susp|syrup|soln|gel|cream|oint|paste|mouthwash|rinse|inj|drops|chewable|er|dr|odt|disintegrating|oral|topical|ophthalmic|otic|nasal|spray|powder|lozenge|film|patch|kit)\b.*$/i', '', $sf));
                $strength = str_replace(' %', '%', $strength);
                $out[] = ['name' => trim($base.' '.$strength), 'form' => $form];
                if (count($out) >= 15) break 2;
            }
        }
        $seen = [];
        return array_values(array_filter($out, function ($m) use (&$seen) { $k = mb_strtolower($m['name']); if (isset($seen[$k])) return false; return $seen[$k] = true; }));
    }
}
