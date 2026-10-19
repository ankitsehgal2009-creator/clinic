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
        return ['password' => 'hashed', 'active' => 'boolean'];
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
