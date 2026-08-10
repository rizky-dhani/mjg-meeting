<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(fn (User $user) => $user->user_id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'user_id',
        'password',
        'name',
        'email',
        'employee_code',
        'initial',
        'company_id',
        'division_id',
        'designation_id',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    // ── Relations ──

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Additional divisions beyond the primary division_id.
     */
    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class);
    }

    /**
     * All divisions this user belongs to: primary division_id plus extras.
     *
     * @return array<int, int>
     */
    public function divisionIds(): array
    {
        return collect([$this->division_id])
            ->merge($this->divisions()->pluck('divisions.id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, Division> */
    public function allDivisions(): Collection
    {
        $primary = $this->division;
        $extras = $this->divisions;

        return collect([$primary])
            ->merge($extras)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id', 'designation_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ── Filament ──

    public function getNameAttribute(): string
    {
        return $this->attributes['name'] ?? $this->getAttributes()['name'] ?? 'User';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
