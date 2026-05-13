<?php

namespace App\Models;

use App\Support\AppAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function navigation(): array
    {
        return AppAccess::navigationForRole($this->role);
    }

    public function hasShopAccess(string $shop): bool
    {
        return collect($this->navigation()['shops'])
            ->contains(fn ($availableShop) => $availableShop['key'] === $shop);
    }

    public function hasPermission(string $permission): bool
    {
        return AppAccess::isShopEnabled(AppAccess::permissionShop($permission))
            && AppAccess::roleCan($this->role, $permission);
    }
}
