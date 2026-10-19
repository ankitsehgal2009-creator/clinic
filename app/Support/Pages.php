<?php

namespace App\Support;

use App\Models\User;

/** Every screen a user can be given. Admin always sees all of them. */
class Pages
{
    public const PAGES = [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'dashboard', 'roles' => ['DOCTOR', 'RECEPTIONIST', 'ASSISTANT']],
        'appointments' => ['label' => 'Appointments', 'route' => 'appointments.index', 'roles' => ['DOCTOR', 'RECEPTIONIST', 'ASSISTANT']],
        'patients' => ['label' => 'Patients', 'route' => 'patients.index', 'roles' => ['DOCTOR', 'RECEPTIONIST', 'ASSISTANT']],
        'import' => ['label' => 'Import old patients', 'route' => 'import.show', 'roles' => ['DOCTOR']],
        'staff' => ['label' => 'Staff', 'route' => 'staff.index', 'roles' => ['DOCTOR']],
        'activity' => ['label' => 'Activity log', 'route' => 'activity', 'roles' => ['DOCTOR']],
        'settings' => ['label' => 'Settings', 'route' => 'settings', 'roles' => []],
    ];

    public const COMING_SOON = [
        'Billing & GST', 'Prescriptions', 'Tooth chart', 'Daily cash closing', 'Online booking & QR', 'WhatsApp portal',
        'Live waiting queue', 'Referral tracking', 'Staff attendance & salary', 'Doctor commissions', 'Marketing & bulk WhatsApp',
    ];

    public const ROLE_LABEL = ['ADMIN' => 'Admin', 'DOCTOR' => 'Doctor', 'RECEPTIONIST' => 'Receptionist', 'ASSISTANT' => 'Assistant'];

    public static function defaults(string $role): array
    {
        if ($role === 'ADMIN') {
            return array_keys(self::PAGES);
        }
        return array_keys(array_filter(self::PAGES, fn ($p) => in_array($role, $p['roles'], true)));
    }

    public static function forUser(User $user): array
    {
        if ($user->role === 'ADMIN') {
            return array_keys(self::PAGES);
        }
        $chosen = $user->pages ? json_decode($user->pages, true) : null;
        if (! is_array($chosen)) {
            return self::defaults($user->role);
        }
        return array_values(array_filter(array_keys(self::PAGES), fn ($k) => in_array($k, $chosen, true)));
    }
}
