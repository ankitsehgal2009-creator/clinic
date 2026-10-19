<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Support\Fmt;
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
        $c = Clinic::create(['name' => 'Smile Craft Dental Clinic', 'address' => 'Shop 4, Sea Breeze CHS, Lokhandwala Road, Andheri West', 'city' => 'Mumbai 400053', 'phone' => '022 2632 4410', 'gstin' => '27AAKFS4821M1Z6', 'patient_prefix' => 'SC-']);
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
        Activity::log($admin, 'Created demo clinic');
        $this->command->info('Demo clinic ready. Sign in with 9820077134 (admin), 9819022417 (doctor) or 9004018822 (receptionist), password demo1234.');
    }
}
