<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Booking;
use Filament\Notifications\Notification;
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

        $this->alreadyCheckedIn = $this->booking->attendance()
            ->where('user_id', auth()->id())
            ->exists();

        if ($this->alreadyCheckedIn) {
            Notification::make()
                ->warning()
                ->title('Already Checked In')
                ->body('You have already recorded your attendance for this meeting.')
                ->send();
        }

        $this->loading = false;
    }

    public function rules(): array
    {
        return [
            'qrToken' => ['required', 'string'],
        ];
    }

    public function checkIn(): void
    {
        $this->validate();

        if (! $this->booking) {
            Notification::make()
                ->danger()
                ->title('Invalid QR Code')
                ->body('This QR code is not valid or the booking has been cancelled.')
                ->send();

            return;
        }

        if ($this->isExpired) {
            Notification::make()
                ->danger()
                ->title('Meeting Expired')
                ->body('This meeting has already ended.')
                ->send();

            return;
        }

        if ($this->alreadyCheckedIn) {
            Notification::make()
                ->warning()
                ->title('Duplicate Check-In')
                ->body('You have already checked in for this meeting.')
                ->send();

            return;
        }

        Attendance::create([
            'booking_id' => $this->booking->id,
            'user_id' => auth()->id(),
            'checked_in_at' => now(),
        ]);

        $this->checkedIn = true;

        Notification::make()
            ->success()
            ->title('Attendance Recorded!')
            ->body('Your check-in has been recorded successfully.')
            ->send();
    }

    public function render()
    {
        return view('livewire.attendance-checkin')
            ->layout('layouts.app');
    }
}
