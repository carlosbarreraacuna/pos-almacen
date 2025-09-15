<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relación con el rol del usuario
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole(string $role): bool
    {
        return $this->role && $this->role->name === $role;
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission(string $permission): bool
    {
        return $this->role && $this->role->hasPermission($permission);
    }

    /**
     * Verificar si el usuario tiene cualquiera de los roles especificados
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->role && in_array($this->role->name, $roles);
    }
}
