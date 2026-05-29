<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Booking Information')
                    ->columns(2)
                    ->schema([
                        Hidden::make('user_id')
                            ->default(fn() => auth()->id()),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Select::make('room_id')
                            ->relationship('room', 'name', fn(Builder $query) => $query->with('location'))
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} ({$record->location?->name})")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabledOn('edit'),
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
                Section::make('Schedule')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('date')
                            ->required()
                            ->disabledOn('edit')
                            ->columnSpanFull()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->default(now())
                            ->live(),
                        TimePicker::make('starts_at')
                            ->required()
                            ->before('ends_at')
                            ->seconds(false)
                            ->format('H:i')
                            ->native(false)
                            ->displayFormat('H:i')
                            ->suffixIcon(Heroicon::Clock)
                            ->default('00:00')
                            ->disabledOn('edit')
                            ->live(),
                        TimePicker::make('ends_at')
                            ->required()
                            ->after('starts_at')
                            ->seconds(false)
                            ->format('H:i')
                            ->native(false)
                            ->displayFormat('H:i')
                            ->suffixIcon(Heroicon::Clock)
                            ->default('00:00')
                            ->disabledOn('edit')
                            ->live(),
                        ActionsComponent::make([
                            Action::make('check_availability')
                                ->label('Check Availability')
                                ->color('info')
                                ->action(function (Get $get) {
                                    $roomId = $get('room_id');
                                    $date = $get('date');
                                    $startsAt = $get('starts_at');
                                    $endsAt = $get('ends_at');

                                    if (! $roomId || ! $date || ! $startsAt || ! $endsAt) {
                                        Notification::make()
                                            ->warning()
                                            ->title('Please fill in all required fields')
                                            ->body('Room, date, start time, and end time are required.')
                                            ->send();

                                        return;
                                    }

                                    $available = Booking::isAvailable($roomId, $date, $startsAt, $endsAt);

                                    if ($available) {
                                        Notification::make()
                                            ->success()
                                            ->title('Room is available!')
                                            ->body('The selected time slot is free.')
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->danger()
                                            ->title('Room is not available')
                                            ->body('This room is already booked for the selected time slot.')
                                            ->send();
                                    }
                                }),
                        ])
                            ->fullWidth()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
