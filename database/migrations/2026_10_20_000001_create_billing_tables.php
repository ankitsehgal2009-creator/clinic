<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Billing & GST and daily cash closing. All money is stored in paise (₹1 = 100) so totals never drift.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $t) {
            $t->string('invoice_prefix', 8)->default('INV');
        });

        // Price list: what the clinic charges, with SAC code and GST rate (0 for treatment, 18 for cosmetic).
        Schema::create('treatment_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->string('name');
            $t->string('sac', 8)->default('999313');
            $t->unsignedBigInteger('price')->default(0);
            $t->unsignedTinyInteger('gst_rate')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('number', 30);                // e.g. SC/26-27/0418
            $t->string('fy', 5);                     // financial year 26-27; numbering restarts every 1 April
            $t->unsignedInteger('seq');
            $t->string('date', 10);
            $t->unsignedBigInteger('taxable')->default(0);
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('cgst')->default(0);
            $t->unsignedBigInteger('sgst')->default(0);
            $t->unsignedBigInteger('total')->default(0);
            $t->unsignedBigInteger('paid')->default(0);
            $t->string('notes', 500)->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->unique(['clinic_id', 'number']);
            $t->unique(['clinic_id', 'fy', 'seq']);
            $t->index(['clinic_id', 'date']);
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('tooth', 40)->nullable();
            $t->string('sac', 8)->nullable();
            $t->unsignedSmallInteger('qty')->default(1);
            $t->unsignedBigInteger('rate');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('taxable');
            $t->unsignedTinyInteger('gst_rate')->default(0);
            $t->unsignedBigInteger('cgst')->default(0);
            $t->unsignedBigInteger('sgst')->default(0);
            $t->unsignedBigInteger('amount');
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $t->string('date', 10);
            $t->string('mode', 10); // CASH | UPI | CARD | BANK | CHEQUE
            $t->unsignedBigInteger('amount');
            $t->string('reference', 100)->nullable();
            $t->foreignId('received_by')->constrained('users');
            $t->timestamps();
            $t->index(['clinic_id', 'date']);
        });

        // Money taken out of the cash drawer during the day (courier, water, small purchases).
        Schema::create('cash_payouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->string('date', 10);
            $t->string('description', 150);
            $t->unsignedBigInteger('amount');
            $t->foreignId('user_id')->constrained();
            $t->timestamps();
            $t->index(['clinic_id', 'date']);
        });

        Schema::create('cash_closings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('clinic_id')->constrained();
            $t->string('date', 10);
            $t->unsignedBigInteger('opening');
            $t->unsignedBigInteger('cash_in');
            $t->unsignedBigInteger('paid_out');
            $t->unsignedBigInteger('expected');
            $t->unsignedBigInteger('counted');
            $t->unsignedBigInteger('kept');       // left in the drawer; tomorrow's opening balance
            $t->text('notes_count')->nullable();  // JSON: how many of each note
            $t->string('reason')->nullable();     // why counted differs from expected
            $t->unsignedBigInteger('upi')->default(0);
            $t->unsignedBigInteger('card')->default(0);
            $t->unsignedBigInteger('other')->default(0);
            $t->foreignId('closed_by')->constrained('users');
            $t->timestamps();
            $t->unique(['clinic_id', 'date']);
        });

        // Clinics that already exist get the usual dental price list to start from.
        $now = now();
        foreach (DB::table('clinics')->pluck('id') as $id) {
            DB::table('treatment_prices')->insert(array_map(fn ($p) => [
                'clinic_id' => $id, 'name' => $p[0], 'price' => $p[1] * 100, 'gst_rate' => $p[2], 'sac' => '999313', 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ], Money::DEFAULT_PRICES));
        }
    }

    public function down(): void
    {
        foreach (['cash_closings', 'cash_payouts', 'payments', 'invoice_items', 'invoices', 'treatment_prices'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('clinics', fn (Blueprint $t) => $t->dropColumn('invoice_prefix'));
    }
};
