<?php

use App\Support\Rx;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Visits with prescriptions, prescription templates, the tooth chart, and online booking.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $t) {
            $t->string('rx_design', 20)->default('classic');      // classic | modern | minimal | bilingual
            $t->unsignedBigInteger('rx_template_id')->nullable(); // default template on a new visit
            $t->boolean('booking_on')->default(false);
            $t->string('booking_slug', 40)->nullable()->unique();  // public link: /book/<slug>
            $t->boolean('booking_approval')->default(false);       // online bookings wait for reception to confirm
            $t->unsignedTinyInteger('booking_days')->default(14);  // how far ahead patients may book
        });

        Schema::create('rx_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->string('name', 80);
            $t->text('meds');                 // JSON list of {name, form, dose, timing, days}
            $t->text('advice')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedInteger('uses')->default(0);
            $t->timestamps();
        });

        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('doctor_id')->nullable()->constrained('users');
            $t->string('date', 10);
            $t->string('complaint')->nullable();
            $t->string('teeth', 60)->nullable();     // FDI numbers, e.g. "36, 37"
            $t->string('procedure')->nullable();
            $t->text('findings')->nullable();
            $t->string('diagnosis')->nullable();
            $t->text('advice')->nullable();
            $t->string('next_visit', 10)->nullable();
            $t->string('design', 20)->default('classic');
            $t->foreignId('rx_template_id')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['clinic_id', 'date']);
        });

        Schema::create('visit_medicines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('form', 20)->nullable();   // Tablet, Capsule, Syrup...
            $t->string('dose', 40)->nullable();   // 1-0-1
            $t->string('timing', 60)->nullable(); // After food
            $t->string('days', 30)->nullable();
        });

        Schema::create('tooth_marks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('tooth');       // FDI number, 11-48 adult, 51-85 milk teeth
            $t->string('state', 12);                // see App\Support\Teeth::STATES
            $t->string('note', 250)->nullable();
            $t->foreignId('updated_by')->constrained('users');
            $t->timestamps();
            $t->unique(['patient_id', 'tooth']);
        });

        // Online bookings are logged with no staff member.
        Schema::table('activities', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->change();
        });

        foreach (DB::table('clinics')->pluck('id') as $id) {
            Rx::seedTemplates($id);
        }
    }

    public function down(): void
    {
        foreach (['tooth_marks', 'visit_medicines', 'visits', 'rx_templates'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('clinics', function (Blueprint $t) {
            $t->dropUnique(['booking_slug']);
            $t->dropColumn(['rx_design', 'rx_template_id', 'booking_on', 'booking_slug', 'booking_approval', 'booking_days']);
        });
    }
};
