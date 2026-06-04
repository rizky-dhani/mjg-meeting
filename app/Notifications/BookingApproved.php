<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingApproved extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $qrCodeUrl = asset('storage/' . $this->booking->qr_code);

        return (new MailMessage)
            ->subject("Booking Approved: {$this->booking->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your booking for **{$this->booking->title}** has been approved.")
            ->line("**Room:** {$this->booking->room->name}")
            ->line("**Location:** {$this->booking->room->location?->name}")
            ->line("**Date:** {$this->booking->starts_at->format('l, M d, Y')}")
            ->line("**Time:** {$this->booking->starts_at->format('H:i')} - {$this->booking->ends_at->format('H:i')}")
            ->line('Scan the QR code below to check in:')
            ->line('<img src="' . $qrCodeUrl . '" alt="QR Code" style="width:200px;height:200px;" />')
            ->action('View Booking', url("/dashboard/bookings/{$this->booking->id}"))
            ->line("This QR code is valid until the end of the meeting day ({$this->booking->ends_at->format('M d, Y')}).");
    }
}
