<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPrice;
use App\Models\User;
use App\Support\Fmt;
use App\Support\Money;
use Illuminate\Database\Seeder;

/** Demo clinic for trying the app. Run: php artisan db:seed */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('mobile', '9820077134')->exists()) {
            $this->command->info('Demo clinic already exists.');
            return;
        }
        $c = Clinic::create(['name' => 'Smile Craft Dental Clinic', 'address' => 'Shop 4, Sea Breeze CHS, Lokhandwala Road, Andheri West', 'city' => 'Mumbai 400053', 'phone' => '022 2632 4410', 'gstin' => '27AAKFS4821M1Z6', 'patient_prefix' => 'SC-', 'invoice_prefix' => 'SC']);
        TreatmentPrice::seedDefaults($c->id);
        $chairs = collect([1, 2, 3])->map(fn ($n) => Chair::create(['clinic_id' => $c->id, 'name' => "Chair $n"]));
        $admin = User::create(['clinic_id' => $c->id, 'name' => 'Vikram Mehta', 'mobile' => '9820077134', 'role' => 'ADMIN', 'password' => 'demo1234']);
        $doc = User::create(['clinic_id' => $c->id, 'name' => 'Dr. Ananya Kulkarni', 'mobile' => '9819022417', 'role' => 'DOCTOR', 'password' => 'demo1234', 'qualification' => 'BDS, MDS (Endodontics)', 'reg_no' => 'A-28417', 'specialisation' => 'Endodontist']);
        User::create(['clinic_id' => $c->id, 'name' => 'Priya Shinde', 'mobile' => '9004018822', 'role' => 'RECEPTIONIST', 'password' => 'demo1234']);
        $people = [
            ['Rohan Deshpande', '9820145567', 'Male', '1992-03-12', 'FATHER', 'Suresh Deshpande'],
            ['Meera Iyer', '9930021456', 'Female', '1997-10-21', 'HUSBAND', 'Karthik Iyer'],
            ['Aditya Joshi', '9892560031', 'Male', '2016-12-14', 'FATHER', 'Nikhil Joshi'],
            ['Pooja Joshi', '9892560032', 'Female', '1989-10-23', 'HUSBAND', 'Nikhil Joshi'],
            ['Lakshmi Narayanan', '9867340021', 'Female', '1965-02-09', 'HUSBAND', 'S. Narayanan'],
        ];
        $pts = [];
        foreach ($people as $i => [$name, $mobile, $gender, $dob, $rel, $relName]) {
            $pts[] = Patient::create(['clinic_id' => $c->id, 'code' => 'SC-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT), 'name' => $name, 'mobile' => $mobile, 'gender' => $gender, 'dob' => $dob, 'age' => Fmt::ageFrom($dob), 'relation_type' => $rel, 'relative_name' => $relName, 'medical_history' => []]);
        }
        $c->update(['next_patient_no' => count($pts) + 1]);
        foreach ([[0, 960, 'RCT sitting'], [1, 1005, 'Scaling'], [2, 1050, 'Consultation'], [3, 1110, 'Braces adjustment']] as [$p, $m, $t]) {
            Appointment::create(['clinic_id' => $c->id, 'patient_id' => $pts[$p]->id, 'chair_id' => $chairs[$p % 3]->id, 'doctor_id' => $doc->id, 'date' => Fmt::today(), 'start_min' => $m, 'treatment' => $t]);
        }
        $today = Fmt::today();
        $fy = Money::fy($today);
        foreach ([
            [0, [['Root canal treatment', '36', 6500, 0], ['Zirconia crown', '36', 11000, 0], ['Teeth whitening (cosmetic)', null, 8000, 18]], ['UPI', 20000]],
            [1, [['Scaling & polishing', null, 1500, 0]], ['CASH', 1500]],
            [3, [['Braces adjustment', null, 1200, 0]], null],
        ] as $n => [$p, $items, $pay]) {
            $lines = array_map(fn ($i) => ['name' => $i[0], 'tooth' => $i[1], 'sac' => '999313', 'qty' => 1, 'rate' => $i[2] * 100, 'gst_rate' => $i[3]] + Money::line(1, $i[2] * 100, 0, $i[3]), $items);
            $inv = Invoice::create([
                'clinic_id' => $c->id, 'patient_id' => $pts[$p]->id, 'date' => $today, 'number' => "SC/$fy/".str_pad($n + 1, 4, '0', STR_PAD_LEFT), 'fy' => $fy, 'seq' => $n + 1,
                'taxable' => array_sum(array_column($lines, 'taxable')), 'cgst' => array_sum(array_column($lines, 'cgst')), 'sgst' => array_sum(array_column($lines, 'sgst')),
                'total' => array_sum(array_column($lines, 'amount')), 'created_by' => $doc->id,
            ]);
            $inv->items()->createMany($lines);
            if ($pay) {
                Payment::create(['clinic_id' => $c->id, 'invoice_id' => $inv->id, 'patient_id' => $pts[$p]->id, 'date' => $today, 'mode' => $pay[0], 'amount' => $pay[1] * 100, 'received_by' => $doc->id]);
                $inv->syncPaid();
            }
        }
        Activity::log($admin, 'Created demo clinic');
        $this->command->info('Demo clinic ready. Sign in with 9820077134 (admin), 9819022417 (doctor) or 9004018822 (receptionist), password demo1234.');
    }
}
