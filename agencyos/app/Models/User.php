<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    protected $attributes = ['is_active' => true, 'is_super_admin' => false];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function agencies()
    {
        return $this->belongsToMany(Agency::class, 'agency_users')->withPivot('role_id')->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if (str_starts_with($permission, 'superadmin.')) {
            return $this->is_super_admin;
        }
        $agency = app(TenantContext::class)->agency();
        if (! $agency || $agency->status !== 'active') {
            return false;
        }
        $membership = $this->agencies()->where('agencies.id', $agency->id)->first();

        return $membership && Role::find($membership->pivot->role_id)?->permissions()->where('name', $permission)->exists();
    }
}
