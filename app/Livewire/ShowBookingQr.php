<?php

namespace App\Livewire;

use App\Models\Booking;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ShowBookingQr extends Component
{
    #[Locked]
    public string $qrToken;

    public ?Booking $booking = null;

    public bool $notFound = false;

    public function mount(string $qrToken): void
    {
        $this->qrToken = $qrToken;
        $this->loadBooking();
    }

    public function loadBooking(): void
    {
        $this->booking = Booking::query()
            ->where('qr_token', $this->qrToken)
            ->with(['room.location'])
            ->first();

        if (! $this->booking) {
            $this->notFound = true;
        }
    }

    public function render()
    {
        return view('livewire.show-booking-qr')
            ->layout('layouts.app', ['title' => config('app.name') . ' - QR Code']);
    }
}
