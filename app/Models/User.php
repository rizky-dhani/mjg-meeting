<?php

namespace App\Models;

use App\Models\Identity\User as IdentityUser;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = ['user_id', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
    // ── Filament user name (delegated to identity DB) ──

    public function getNameAttribute(): string
    {
        if ($this->relationLoaded('identity')) {
            return $this->identity?->name ?? 'User';
        }

        // Lazy load with safety check
        try {
            $identity = $this->identity;
            return $identity?->name ?? 'User';
        } catch (\Throwable) {
            return 'User';
        }
    }

    // ── Identity relation (profile data from identity DB) ──

    public function identity(): BelongsTo
    {
        return $this->belongsTo(IdentityUser::class, 'user_id', 'userId');
    }

    // ── Local relations ──

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ── Filament ──

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
