<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ToothController;
use App\Http\Controllers\VisitFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? auth()->user()->homeUrl() : '/login'));

// Public booking page for patients: no login. Only free times are shown, never other patients' names.
Route::get('/book/{slug}', [BookingController::class, 'page'])->name('book.page');
Route::get('/book/{slug}/slots', [BookingController::class, 'freeSlots'])->name('book.slots');
Route::post('/book/{slug}', [BookingController::class, 'book'])->middleware('throttle:booking')->name('book.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/setup', [AuthController::class, 'showSetup'])->name('setup');
    Route::post('/setup', [AuthController::class, 'setup'])->middleware('throttle:setup');
});

Route::middleware(['auth', 'page'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/no-access', 'no-access')->name('no-access');
    Route::get('/appearance', [AppearanceController::class, 'show'])->name('appearance');
    Route::post('/appearance', [AppearanceController::class, 'save']);

    Route::get('/dashboard', DashboardController::class)->middleware('page:dashboard')->name('dashboard');

    Route::middleware('page:appointments')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/new', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'status'])->name('appointments.status');
        Route::post('/appointments/{appointment}/move', [AppointmentController::class, 'move'])->name('appointments.move');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
        Route::get('/api/slots', [ApiController::class, 'slots']);
        Route::get('/api/appointments/counts', [ApiController::class, 'counts']);
        Route::get('/api/patients/search', [ApiController::class, 'search']);
    });

    Route::middleware('page:import')->group(function () {
        Route::get('/patients/import', [ImportController::class, 'show'])->name('import.show');
        Route::get('/patients/import/sample', [ImportController::class, 'sample'])->name('import.sample');
        Route::post('/patients/import/read', [ImportController::class, 'read'])->name('import.read');
        Route::post('/patients/import', [ImportController::class, 'store'])->name('import.store');
    });

    Route::middleware('page:patients')->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/new', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('/api/dash/patients', [ApiController::class, 'search']);
        Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
        Route::post('/patients/{patient}/edit', [PatientController::class, 'update'])->name('patients.update');
        Route::post('/patients/{patient}/active', [PatientController::class, 'active'])->name('patients.active');
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');
    });

    Route::middleware('page:billing')->group(function () {
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/new', [BillingController::class, 'create'])->name('billing.create');
        Route::post('/billing', [BillingController::class, 'store'])->name('billing.store');
        Route::get('/billing/prices', [BillingController::class, 'prices'])->name('billing.prices');
        Route::post('/billing/prices', [BillingController::class, 'savePrice'])->name('billing.prices.store');
        Route::post('/billing/prices/{price}', [BillingController::class, 'savePrice'])->name('billing.prices.update');
        Route::post('/billing/prices/{price}/active', [BillingController::class, 'priceActive'])->name('billing.prices.active');
        Route::get('/billing/{invoice}', [BillingController::class, 'show'])->whereNumber('invoice')->name('billing.show');
        Route::post('/billing/{invoice}/pay', [BillingController::class, 'pay'])->name('billing.pay');
        Route::post('/billing/{invoice}/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::delete('/billing/{invoice}', [BillingController::class, 'destroy'])->name('billing.destroy');
        Route::delete('/payments/{payment}', [BillingController::class, 'unpay'])->name('payments.destroy');
        Route::get('/api/billing/patients', [ApiController::class, 'search']);
    });

    Route::middleware('page:prescriptions')->group(function () {
        Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('rx.index');
        Route::get('/prescriptions/new', [PrescriptionController::class, 'create'])->name('rx.create');
        Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('rx.store');
        Route::get('/prescriptions/templates', [PrescriptionController::class, 'templates'])->name('rx.templates');
        Route::post('/prescriptions/templates', [PrescriptionController::class, 'saveTemplate'])->name('rx.templates.store');
        Route::post('/prescriptions/templates/{template}', [PrescriptionController::class, 'saveTemplate'])->name('rx.templates.update');
        Route::post('/prescriptions/templates/{template}/active', [PrescriptionController::class, 'templateActive'])->name('rx.templates.active');
        Route::post('/prescriptions/templates/{template}/default', [PrescriptionController::class, 'templateDefault'])->name('rx.templates.default');
        Route::delete('/prescriptions/templates/{template}', [PrescriptionController::class, 'destroyTemplate'])->name('rx.templates.destroy');
        Route::get('/prescriptions/{visit}', [PrescriptionController::class, 'show'])->whereNumber('visit')->name('rx.show');
        Route::get('/prescriptions/{visit}/edit', [PrescriptionController::class, 'edit'])->name('rx.edit');
        Route::post('/prescriptions/{visit}', [PrescriptionController::class, 'update'])->name('rx.update');
        Route::post('/prescriptions/{visit}/files', [VisitFileController::class, 'store'])->name('visitfiles.store');
        Route::get('/visit-files/{file}', [VisitFileController::class, 'show'])->name('visitfiles.show');
        Route::delete('/visit-files/{file}', [VisitFileController::class, 'destroy'])->name('visitfiles.destroy');
        Route::get('/api/rx/patients', [ApiController::class, 'search']);
    });

    Route::middleware('page:teeth')->group(function () {
        Route::get('/tooth-chart', [ToothController::class, 'index'])->name('teeth.index');
        Route::post('/tooth-chart/{patient}', [ToothController::class, 'save'])->name('teeth.save');
        Route::get('/api/teeth/patients', [ApiController::class, 'search']);
    });

    Route::middleware('page:booking')->group(function () {
        Route::get('/online-booking', [BookingController::class, 'index'])->name('booking.index');
        Route::post('/online-booking', [BookingController::class, 'save'])->name('booking.save');
        Route::get('/api/booking/slots', [ApiController::class, 'slots']);
    });

    Route::middleware('page:cash')->group(function () {
        Route::get('/cash', [CashController::class, 'show'])->name('cash.show');
        Route::post('/cash/payouts', [CashController::class, 'payout'])->name('cash.payout');
        Route::delete('/cash/payouts/{payout}', [CashController::class, 'removePayout'])->name('cash.payout.destroy');
        Route::post('/cash/close', [CashController::class, 'close'])->name('cash.close');
        Route::post('/cash/reopen', [CashController::class, 'reopen'])->name('cash.reopen');
    });

    Route::middleware('page:staff')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::post('/staff/{user}/active', [StaffController::class, 'active'])->name('staff.active');
        Route::post('/staff/{user}/pages', [StaffController::class, 'pages'])->name('staff.pages');
    });

    Route::get('/activity', ActivityController::class)->middleware('page:activity')->name('activity');

    Route::middleware('page:settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
        Route::post('/settings', [SettingsController::class, 'save']);
        Route::post('/settings/logo', [SettingsController::class, 'logo'])->name('settings.logo');
    });
});
