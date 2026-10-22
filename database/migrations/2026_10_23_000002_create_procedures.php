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
        // The clinic's own list for "Procedure done" on a visit
        Schema::create('procedures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['clinic_id', 'name']);
        });
        foreach (DB::table('clinics')->pluck('id') as $id) {
            Rx::seedProcedures($id);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
