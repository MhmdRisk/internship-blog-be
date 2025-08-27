<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        // 'OTP',
    ];
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Appended attributes for serialization.
     * Adds computed role_name for convenience.
     *
     * @var list<string>
     */
    protected $appends = [
        'role_name',
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

    // JWT Methods
    public function getJWTIdentifier() {
        return $this->getKey();
    }

    public function getJWTCustomClaims() {
        return [];
    }

    /**
     * Keep role_name column in sync when role_id changes.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if ($user->isDirty('role_id')) {
                $role = Role::find($user->role_id);
                $user->role_name = $role ? $role->name : null;
            }
        });
    }

    public function role() {
        return $this->belongsTo(Role::class);
    }

    public function hasRole($role) {
        $rel = $this->role;
        return $rel && ($rel->name === $role);
    }

    public function hasPermission($permission) {
        $role = $this->role;
        if (!$role) return false;
        return $role->permissions()->where('name', $permission)->exists();
    }

    /**
     * Accessor: role name (from related role).
     *
     * @return string|null
     */
    public function getRoleNameAttribute()
    {
        return optional($this->role)->name;
    }

}
