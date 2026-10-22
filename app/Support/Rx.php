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
}
