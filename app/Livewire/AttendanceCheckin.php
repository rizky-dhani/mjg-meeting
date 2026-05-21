<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Booking;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AttendanceCheckin extends Component
{
    #[Locked]
    public string $qrToken;

    public ?Booking $booking = null;

    public bool $alreadyCheckedIn = false;

    public bool $isExpired = false;

    public bool $checkedIn = false;

    public bool $loading = true;

    public function mount(string $qrToken): void
    {
        $this->qrToken = $qrToken;
        $this->loadBooking();
    }

    public function loadBooking(): void
    {
        $this->booking = Booking::query()
            ->where('qr_token', $this->qrToken)
            ->whereHas('approvals', function ($q) {
                $q->where('key', 'booking_approval')
                  ->where('status', \App\Support\Approvals\ApprovalStatus\BookingApprovalStatus::Approved->value);
            })
            ->with(['room.location', 'attendance'])
            ->first();

        if (! $this->booking) {
            $this->booking = null;
            $this->loading = false;

            return;
        }

        if ($this->booking->isExpired()) {
            $this->isExpired = true;
            $this->loading = false;

            return;
        }

        $this->alreadyCheckedIn = $this->booking->attendance()
            ->where('user_id', auth()->id())
            ->exists();

        $this->loading = false;
    }

    public function checkIn(): void
    {
        if (! $this->booking || $this->isExpired || $this->alreadyCheckedIn) {
            return;
        }

        Attendance::create([
            'booking_id' => $this->booking->id,
            'user_id' => auth()->id(),
            'checked_in_at' => now(),
        ]);

        $this->checkedIn = true;
    }

    public function render()
    {
        return view('livewire.attendance-checkin')
            ->layout('layouts.app');
    }
}
