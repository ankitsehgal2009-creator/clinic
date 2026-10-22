<?php

namespace App\Models;

use App\Support\Pages;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'is_owner' => 'boolean', 'prefs' => 'array'];
    }

    /** Colour themes and other look options each person picks for themselves. */
    public const THEMES = [
        'teal' => 'Teal', 'ocean' => 'Ocean blue', 'indigo' => 'Indigo', 'plum' => 'Plum',
        'rose' => 'Rose', 'forest' => 'Forest green', 'sunset' => 'Sunset', 'graphite' => 'Graphite',
    ];
    public const MODES = ['auto' => 'Same as device', 'light' => 'Light', 'dark' => 'Dark'];
    public const MENUS = ['dark' => 'Dark menu', 'light' => 'Light menu', 'color' => 'Coloured menu'];
    public const SIZES = ['normal' => 'Normal', 'large' => 'Large'];
    public const CORNERS = ['round' => 'Rounded', 'soft' => 'Extra round', 'sharp' => 'Square'];

    /** One look option, falling back to the default when unset or unknown. */
    public function pref(string $key): string
    {
        $lists = ['theme' => self::THEMES, 'mode' => self::MODES, 'menu' => self::MENUS, 'size' => self::SIZES, 'corners' => self::CORNERS];
        $v = $this->prefs[$key] ?? null;
        return isset($lists[$key][$v]) ? $v : array_key_first($lists[$key]);
    }

    /** Username made from a name: "Dr. Ananya Kulkarni" becomes "ananya". */
    public static function slug(string $name): string
    {
        $name = preg_replace('/^\s*dr\.?\s+/i', '', $name);
        $first = strtolower(preg_replace('/[^a-z0-9]/i', '', explode(' ', trim($name))[0] ?? ''));
        return str_pad(substr($first, 0, 24) ?: 'user', 3, '0');
    }

    /** Lower-cased username, or null when it isn't 3-30 letters, digits, dots or underscores. */
    public static function cleanUsername(?string $u): ?string
    {
        $u = strtolower(trim((string) $u));
        return preg_match('/^[a-z0-9._]{3,30}$/', $u) ? $u : null;
    }

    /** Admin and the owner doctor may change clinic settings and the logo. */
    /** Admin and doctors can change a patient's details after they are added. */
    public function canEditPatients(): bool
    {
        return $this->isAdmin() || $this->role === 'DOCTOR';
    }

    public function canManageClinic(): bool
    {
        return $this->isAdmin() || ($this->role === 'DOCTOR' && $this->is_owner);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    /** Page keys this person may open. Admin always has every page. */
    public function pageKeys(): array
    {
        return Pages::forUser($this);
    }

    public function canOpen(string $page): bool
    {
        return in_array($page, $this->pageKeys(), true);
    }

    /** First page this person may open, used after sign-in. */
    public function homeUrl(): string
    {
        $first = $this->pageKeys()[0] ?? null;
        return $first ? route(Pages::PAGES[$first]['route']) : route('no-access');
    }

    public function roleLabel(): string
    {
        return Pages::ROLE_LABEL[$this->role] ?? $this->role;
    }
}
