<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Booking;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class AttendanceCheckin extends Component
{
    #[Locked]
    public string $qrToken;

    #[Locked]
    public bool $isGuest = false;

    public ?Booking $booking = null;

    public bool $alreadyCheckedIn = false;

    public bool $isExpired = false;

    public bool $checkedIn = false;

    public bool $confirming = false;

    public bool $loading = true;

    #[Rule('required|string|max:255')]
    public string $guestName = '';

    #[Rule('nullable|string|max:255')]
    public string $guestFrom = '';

    #[Rule('nullable|string|max:255')]
    public string $guestDesignation = '';

    public function mount(string $qrToken): void
    {
        $this->qrToken = $qrToken;
        $this->isGuest = ! auth()->check();
        $this->loadBooking();
    }

    public function loadBooking(): void
    {
        $this->booking = Booking::query()
            ->where('qr_token', $this->qrToken)
            ->whereHas('approvals', function ($q) {
                $q->where('status', 'approved');
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

        if (auth()->check()) {
            $this->alreadyCheckedIn = $this->booking->attendance()
                ->where('user_id', auth()->id())
                ->exists();
        }

        $this->loading = false;
    }

    public function confirmCheckIn(): void
    {
        if ($this->alreadyCheckedIn || $this->isExpired || ! $this->booking) {
            return;
        }

        $this->confirming = true;
    }

    public function cancelCheckIn(): void
    {
        $this->confirming = false;
    }

    public function checkIn(): void
    {
        if (! $this->booking || $this->isExpired) {
            $this->confirming = false;

            return;
        }

        if (auth()->check()) {
            if ($this->alreadyCheckedIn) {
                $this->confirming = false;

                return;
            }

            Attendance::create([
                'booking_id' => $this->booking->id,
                'user_id' => auth()->id(),
                'checked_in_at' => now(),
            ]);
        } else {
            $this->validate();

            $alreadyCheckedIn = $this->booking->attendance()
                ->whereNull('user_id')
                ->where('guest_name', $this->guestName)
                ->exists();

            if ($alreadyCheckedIn) {
                $this->alreadyCheckedIn = true;

                return;
            }

            Attendance::create([
                'booking_id' => $this->booking->id,
                'user_id' => null,
                'guest_name' => $this->guestName,
                'guest_from' => $this->guestFrom,
                'guest_designation' => $this->guestDesignation,
                'checked_in_at' => now(),
            ]);
        }

        $this->checkedIn = true;
        $this->confirming = false;
    }

    public function render()
    {
        return view('livewire.attendance-checkin')
            ->layout('layouts.app');
    }
}
