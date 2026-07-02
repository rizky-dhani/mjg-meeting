<?php

namespace App\Notifications;

use App\Filament\Pages\Approvals;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingNeedsApproval extends Notification implements ShouldQueue
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
            ->subject("Approval Needed: {$this->booking->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line('A booking requires your approval.')
            ->line("**{$this->booking->title}**")
            ->line("**Room:** {$this->booking->room->name}")
            ->line("**Location:** {$this->booking->room->location?->name}")
            ->line("**Date:** {$this->booking->starts_at->format('l, M d, Y')}")
            ->line("**Time:** {$this->booking->starts_at->format('H:i')} - {$this->booking->ends_at->format('H:i')}")
            ->line("**Requested by:** {$this->booking->user->name}")
            ->action('Review Booking', url(Approvals::getUrl()));
    }
}
