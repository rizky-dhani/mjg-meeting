<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceFactory> */
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'booking_id',
        'user_id',
        'guest_name',
        'guest_from',
        'guest_designation',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function attendeeType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_id ? 'staff' : 'guest',
        );
    }

    public function scopeGuests(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }
}
