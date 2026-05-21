<?php

namespace App\Models;

use App\Support\Approvals\Approval\SimpleApprovalBy;
use App\Support\Approvals\Approval\SimpleApprovalFlow;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Traits\HasApprovals;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model implements Approvable
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory, HasApprovals;

    protected $fillable = [
        'room_id',
        'user_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'qr_token',
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function getApprovalFlows(): array
    {
        return [
            'booking_approval' => SimpleApprovalFlow::make()
                ->approvalStatus(BookingApprovalStatus::cases())
                ->approvalBys([
                    SimpleApprovalBy::make('requester')
                        ->any()
                        ->atLeast(1),
                    SimpleApprovalBy::make('management')
                        ->role('Admin')
                        ->orRole('Super Admin')
                        ->atLeast(1),
                ]),
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isExpired(): bool
    {
        return $this->ends_at->endOfDay()->isPast();
    }

    public function scopePending($query)
    {
        return $query->whereHas('approvals', function ($q) {
            $q->where('key', 'booking_approval');
        });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
