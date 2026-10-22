<?php

use App\Support\Rx;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The clinic's medicine library: names suggested while writing a prescription, with their usual dose
        Schema::create('medicines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('form', 20)->nullable();
            $t->string('dose', 40)->nullable();
            $t->string('timing', 60)->nullable();
            $t->string('days', 30)->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedInteger('uses')->default(0);
            $t->timestamps();
            $t->unique(['clinic_id', 'name']);
        });
        foreach (DB::table('clinics')->pluck('id') as $id) {
            Rx::seedMedicines($id);
            // Medicines the clinic already wrote on visits or templates join the library too.
            $used = DB::table('visit_medicines')->join('visits', 'visits.id', '=', 'visit_medicines.visit_id')->where('visits.clinic_id', $id)
                ->get(['visit_medicines.name', 'visit_medicines.form', 'visit_medicines.dose', 'visit_medicines.timing', 'visit_medicines.days'])->map(fn ($m) => (array) $m)->all();
            foreach (DB::table('rx_templates')->where('clinic_id', $id)->pluck('meds') as $json) {
                $used = array_merge($used, json_decode($json ?: '[]', true) ?: []);
            }
            Rx::learnMedicines($id, $used);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
