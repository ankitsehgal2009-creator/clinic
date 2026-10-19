<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Usernames (so two people can share one mobile number), clinic logo, owner doctor, and each person's own look.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $t) {
            $t->mediumText('logo')->nullable(); // small image as data URL
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 30)->nullable()->unique()->after('name');
            $t->boolean('is_owner')->default(false)->after('role'); // owner doctor: may change clinic settings and logo
            $t->text('prefs')->nullable();                          // JSON: theme, mode, menu, size
            $t->dropUnique(['mobile']);
            $t->index('mobile');
        });

        // Everyone who already has an account gets a username made from their name.
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $u) {
            $base = \App\Models\User::slug($u->name);
            $name = $base;
            for ($n = 2; isset($taken[$name]); $n++) {
                $name = $base.$n;
            }
            $taken[$name] = true;
            DB::table('users')->where('id', $u->id)->update(['username' => $name]);
        }
        // The first doctor of each clinic is its owner doctor.
        foreach (DB::table('users')->where('role', 'DOCTOR')->selectRaw('MIN(id) as id')->groupBy('clinic_id')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['is_owner' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['mobile']);
            $t->unique('mobile');
            $t->dropColumn(['username', 'is_owner', 'prefs']);
        });
        Schema::table('clinics', fn (Blueprint $t) => $t->dropColumn('logo'));
    }
};
