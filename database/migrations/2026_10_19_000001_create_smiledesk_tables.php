<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// All SmileDesk tables. Dates are YYYY-MM-DD strings in India time; times are minutes from midnight.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('address');
            $t->string('city');
            $t->string('phone', 30);
            $t->string('gstin', 15)->nullable();
            $t->string('patient_prefix', 6)->default('P-');
            $t->unsignedInteger('next_patient_no')->default(1);
            $t->unsignedSmallInteger('morning_open')->default(600);   // 10:00
            $t->unsignedSmallInteger('morning_close')->default(840);  // 14:00
            $t->unsignedSmallInteger('evening_open')->default(960);   // 16:00
            $t->unsignedSmallInteger('evening_close')->default(1260); // 21:00
            $t->unsignedTinyInteger('slot_minutes')->default(15);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->string('name');
            $t->string('mobile', 10)->unique();
            $t->string('password');
            $t->string('role', 20); // ADMIN | DOCTOR | RECEPTIONIST | ASSISTANT
            $t->boolean('active')->default(true);
            $t->string('qualification')->nullable();
            $t->string('reg_no')->nullable();
            $t->string('email')->nullable();
            $t->string('specialisation')->nullable();
            $t->mediumText('photo')->nullable(); // small JPEG as data URL
            $t->text('pages')->nullable();       // JSON list of page keys; null means role defaults
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('patients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->string('code', 20);
            $t->string('name');
            $t->string('mobile', 10);
            $t->string('gender', 10)->nullable();
            $t->string('dob', 10)->nullable();
            $t->unsignedTinyInteger('age')->nullable();
            $t->string('relation_type', 10)->nullable(); // FATHER | HUSBAND | WIFE | MOTHER
            $t->string('relative_name')->nullable();
            $t->string('address')->nullable();
            $t->text('medical_history')->nullable(); // JSON list
            $t->string('old_id', 50)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['clinic_id', 'code']);
            $t->index(['clinic_id', 'name']);
            $t->index(['clinic_id', 'mobile']);
        });

        Schema::create('chairs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->string('name', 50);
            $t->boolean('active')->default(true);
        });

        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chair_id')->nullable()->constrained();
            $t->foreignId('doctor_id')->nullable()->constrained('users');
            $t->string('date', 10);
            $t->unsignedSmallInteger('start_min');
            $t->unsignedSmallInteger('duration')->default(15);
            $t->string('treatment')->nullable();
            $t->string('status', 12)->default('BOOKED'); // BOOKED | WAITING | IN_CHAIR | COMPLETED | CANCELLED | NO_SHOW
            $t->string('source', 10)->default('PHONE');  // PHONE | WALK_IN | ONLINE
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['clinic_id', 'date']);
        });

        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->text('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['clinic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['activities', 'appointments', 'chairs', 'patients', 'users', 'clinics'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
