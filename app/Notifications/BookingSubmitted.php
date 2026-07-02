<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingSubmitted extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("Booking Submitted: {$this->booking->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your booking for **{$this->booking->title}** has been submitted.")
            ->line("**Room:** {$this->booking->room->name}")
            ->line("**Location:** {$this->booking->room->location?->name}")
            ->line("**Date:** {$this->booking->starts_at->format('l, M d, Y')}")
            ->line("**Time:** {$this->booking->starts_at->format('H:i')} - {$this->booking->ends_at->format('H:i')}")
            ->line('Your booking is pending approval. You will be notified once it is reviewed.')
            ->action('View Booking', url("/dashboard/bookings/{$this->booking->id}"));
    }
}
