<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photos of handwritten prescriptions and X-rays attached to a visit. Files live in storage/app/private.
        Schema::create('visit_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $t->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10); // RX or XRAY
            $t->string('path');
            $t->string('thumb')->nullable();
            $t->string('name', 150)->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['visit_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_files');
    }
};
