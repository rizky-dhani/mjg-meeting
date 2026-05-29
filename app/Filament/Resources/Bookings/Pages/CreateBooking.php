<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        if (! Booking::isAvailable($data['room_id'], $data['date'], $data['starts_at'], $data['ends_at'])) {
            Notification::make()
                ->danger()
                ->title('Room is not available')
                ->body('This room has been booked by someone else since you checked. Please review the schedule.')
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Booking created')
            ->body('The booking has been created successfully.');
    }
}