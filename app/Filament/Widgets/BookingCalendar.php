<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Room;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\CreateAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BookingCalendar extends CalendarWidget
{
    protected CalendarViewType $calendarView = CalendarViewType::ResourceTimeGridWeek;

    protected bool $dateClickEnabled = true;

    protected bool $eventClickEnabled = true;

    protected ?string $defaultEventClickAction = 'view';

    public function getEvents(FetchInfo $info): Collection|Builder|array
    {
        return Booking::query()
            ->with('room')
            ->whereDate('starts_at', '>=', $info->start)
            ->whereDate('ends_at', '<=', $info->end)
            ->whereDoesntHave('approvals', function ($q) {
                $q->where('status', 'denied');
            });
    }

    public function getResources(): Collection|Builder|array
    {
        return Room::query()->with('location');
    }

    public function createBookingAction(): CreateAction
    {
        return $this->createAction(Booking::class)
            ->schema([
                Select::make('room_id')
                    ->label('Room')
                    ->options(Room::pluck('name', 'id'))
                    ->required(),
                \Filament\Forms\Components\TextInput::make('title')
                    ->required(),
                \Filament\Forms\Components\Textarea::make('description'),
                DatePicker::make('date')
                    ->required(),
                TimePicker::make('starts_at')
                    ->required(),
                TimePicker::make('ends_at')
                    ->required(),
            ]);
    }

    protected function getDateClickContextMenuActions(): array
    {
        return [
            $this->createBookingAction(),
        ];
    }
}
