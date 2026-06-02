<?php

namespace App\Models;

use App\Models\ApprovalFlow;
use App\Support\Approvals\Evaluation\ApprovalState;
use App\Support\Approvals\Traits\HasApprovalFlow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory, HasApprovalFlow;

    protected $fillable = [
        'booking_number',
        'room_id',
        'user_id',
        'booker_id',
        'title',
        'description',
        'date',
        'starts_at',
        'ends_at',
        'qr_token',
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    protected function startsAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null
                ? Carbon::parse($this->date->format('Y-m-d') . ' ' . $value)
                : null,
            set: fn ($value) => $value !== null
                ? Carbon::parse($value)->format('H:i:s')
                : null,
        );
    }

    protected function endsAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null
                ? Carbon::parse($this->date->format('Y-m-d') . ' ' . $value)
                : null,
            set: fn ($value) => $value !== null
                ? Carbon::parse($value)->format('H:i:s')
                : null,
        );
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booker_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isExpired(): bool
    {
        return $this->ends_at->isPast();
    }

    public static function isAvailable(
        int $roomId,
        Carbon|string $date,
        Carbon|string $startsAt,
        Carbon|string $endsAt,
        ?int $excludeBookingId = null,
    ): bool
    {
        $flow = ApprovalFlow::where('model_type', static::class)->first();
        $flowName = $flow?->name ?? 'booking_approval';

        return ! static::where('room_id', $roomId)
            ->where('date', $date)
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where(function ($query) use ($flowName) {
                $query->whereDoesntHave('approvals', function ($q) use ($flowName) {
                    $q->where('key', $flowName)
                      ->whereIn('status', ['rejected', 'denied']);
                });
            })
            ->where(function ($query) use ($startsAt, $endsAt) {
                $query->whereBetween('starts_at', [$startsAt, $endsAt])
                    ->orWhereBetween('ends_at', [$startsAt, $endsAt])
                    ->orWhere(function ($q) use ($startsAt, $endsAt) {
                        $q->where('starts_at', '<=', $startsAt)
                          ->where('ends_at', '>=', $endsAt);
                    });
            })
            ->exists();
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    protected static function booted(): void
    {
        static::created(function (Booking $booking) {
            $booking->booking_number = sprintf('MJG-BK-%s-%06d', $booking->date->format('Y'), $booking->id);
            $booking->save();
        });

        static::deleting(function (Booking $booking) {
            if (! $booking->isPending()) {
                throw new \Exception('Only bookings with pending status can be deleted.');
            }
        });
    }
}
