<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Room;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\Rule;

class QuickBookingWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-booking-widget';

    #[Rule('required|string|max:255')]
    public string $title = '';

    #[Rule('required|integer|exists:rooms,id')]
    public ?int $room_id = null;

    #[Rule('required|date|after_or_equal:today')]
    public string $date = '';

    #[Rule('required|date_format:H:i')]
    public string $starts_at = '';

    #[Rule('required|date_format:H:i|after:starts_at')]
    public string $ends_at = '';

    #[Rule('nullable|string|max:1000')]
    public string $description = '';

    public bool $created = false;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->starts_at = now()->format('H:i');
        $this->ends_at = now()->addHour()->format('H:i');
    }

    public function getRooms(): array
    {
        return Room::with('location')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => $room->id,
                'label' => $room->location
                    ? "{$room->name} ({$room->location->name})"
                    : $room->name,
            ])
            ->toArray();
    }

    public function create(): void
    {
        $this->validate();

        $available = Booking::isAvailable(
            $this->room_id,
            $this->date,
            $this->starts_at,
            $this->ends_at,
        );

        if (! $available) {
            Notification::make()
                ->title('Room not available')
                ->body('The selected room is already booked for this time slot.')
                ->danger()
                ->send();

            return;
        }

        Booking::create([
            'title' => $this->title,
            'room_id' => $this->room_id,
            'user_id' => auth()->id(),
            'booker_id' => auth()->id(),
            'date' => $this->date,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'description' => $this->description ?: null,
        ]);

        Notification::make()
            ->title('Booking created successfully')
            ->success()
            ->send();

        $this->resetForm();
    }

    public function checkAvailability(): void
    {
        $this->validateOnly('room_id');
        $this->validateOnly('date');
        $this->validateOnly('starts_at');
        $this->validateOnly('ends_at');

        $available = Booking::isAvailable(
            $this->room_id,
            $this->date,
            $this->starts_at,
            $this->ends_at,
        );

        if ($available) {
            Notification::make()
                ->title('Room is available')
                ->body('The selected time slot is free.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Room is not available')
                ->body('This room is already booked for the selected time slot.')
                ->warning()
                ->send();
        }
    }

    private function resetForm(): void
    {
        $this->reset('title', 'room_id', 'description');
        $this->date = now()->format('Y-m-d');
        $this->starts_at = now()->format('H:i');
        $this->ends_at = now()->addHour()->format('H:i');
        $this->created = true;
    }
}
