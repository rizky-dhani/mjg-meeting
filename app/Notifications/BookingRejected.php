<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Booking Rejected: {$this->booking->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your booking for **{$this->booking->title}** has been rejected.")
            ->line("**Room:** {$this->booking->room->name}")
            ->line("**Date:** {$this->booking->starts_at->format('l, M d, Y')}")
            ->line("**Time:** {$this->booking->starts_at->format('H:i')} - {$this->booking->ends_at->format('H:i')}");

        if ($this->reason) {
            $mail->line("**Reason:** {$this->reason}");
        }

        return $mail
            ->line('Please contact your administrator if you have questions.')
            ->action('View Bookings', url('/dashboard/bookings'));
    }
}
