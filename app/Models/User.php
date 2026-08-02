<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEKNISI = 'teknisi';

    public const ROLES = [
        self::ROLE_SUPERADMIN,
        self::ROLE_ADMIN,
        self::ROLE_TEKNISI,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTeknisi(): bool
    {
        return $this->role === self::ROLE_TEKNISI;
    }

    public function canWrite(): bool
    {
        return ! $this->isTeknisi();
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperadmin() || $this->isAdmin();
    }

    public function canDeleteUser(self $target): bool
    {
        if (! $this->canManageUsers()) {
            return false;
        }

        if ($this->id === $target->id) {
            return false;
        }

        if ($target->isSuperadmin() && ! $this->isSuperadmin()) {
            return false;
        }

        return true;
    }

    public function canEditUser(self $target): bool
    {
        if (! $this->canManageUsers()) {
            return false;
        }

        if ($target->isSuperadmin() && ! $this->isSuperadmin()) {
            return false;
        }

        return true;
    }

    public function assignableRoles(): array
    {
        if ($this->isSuperadmin()) {
            return self::ROLES;
        }

        if ($this->isAdmin()) {
            return [self::ROLE_ADMIN, self::ROLE_TEKNISI];
        }

        return [];
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPERADMIN => 'Superadmin',
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_TEKNISI => 'Teknisi',
            default => $this->role,
        };
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return Str::upper(Str::substr($parts[0], 0, 2));
        }

        return Str::upper(Str::substr($parts[0], 0, 1).Str::substr($parts[1], 0, 1));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ?: null;
    }

    public function deleteAvatarFile(): void
    {
        if (! $this->avatar || ! str_starts_with($this->avatar, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(str_replace('/storage/', '', $this->avatar));
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_label' => $this->roleLabel(),
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatarUrl(),
            'initials' => $this->initials(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function roleOptions(?self $actor = null): array
    {
        $all = [
            [
                'value' => self::ROLE_SUPERADMIN,
                'label' => 'Superadmin',
                'description' => 'Akses penuh ke seluruh sistem',
            ],
            [
                'value' => self::ROLE_ADMIN,
                'label' => 'Admin',
                'description' => 'Akses penuh, kecuali hapus akun Superadmin',
            ],
            [
                'value' => self::ROLE_TEKNISI,
                'label' => 'Teknisi',
                'description' => 'Hanya dapat melihat data (read only)',
            ],
        ];

        if (! $actor) {
            return $all;
        }

        $allowed = $actor->assignableRoles();

        return array_values(array_filter(
            $all,
            fn (array $role) => in_array($role['value'], $allowed, true)
        ));
    }
}
