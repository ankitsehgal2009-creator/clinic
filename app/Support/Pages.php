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
        'prescriptions' => ['label' => 'Prescriptions', 'route' => 'rx.index', 'roles' => ['DOCTOR']],
        'teeth' => ['label' => 'Tooth chart', 'route' => 'teeth.index', 'roles' => ['DOCTOR', 'ASSISTANT']],
        'billing' => ['label' => 'Billing & GST', 'route' => 'billing.index', 'roles' => ['DOCTOR']],
        'cash' => ['label' => 'Daily cash closing', 'route' => 'cash.show', 'roles' => ['DOCTOR']],
        'booking' => ['label' => 'Online booking & QR', 'route' => 'booking.index', 'roles' => ['DOCTOR', 'RECEPTIONIST']],
        'import' => ['label' => 'Import old patients', 'route' => 'import.show', 'roles' => ['DOCTOR']],
        'staff' => ['label' => 'Staff', 'route' => 'staff.index', 'roles' => ['DOCTOR']],
        'activity' => ['label' => 'Activity log', 'route' => 'activity', 'roles' => ['DOCTOR']],
        'settings' => ['label' => 'Settings', 'route' => 'settings', 'roles' => []],
    ];

    public const COMING_SOON = [
        'WhatsApp portal',
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
            $keys = self::defaults($user->role);
            if ($user->is_owner) {
                $keys[] = 'settings';
            }
            return array_values(array_intersect(array_keys(self::PAGES), $keys));
        }
        return array_values(array_filter(array_keys(self::PAGES), fn ($k) => in_array($k, $chosen, true)));
    }
}
