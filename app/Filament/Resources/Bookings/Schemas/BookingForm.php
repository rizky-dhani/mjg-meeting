<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Booking;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')
                    ->relationship('room', 'name', fn(Builder $query) => $query->with('location'))
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} ({$record->location?->name})")
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabledOn('edit')
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $startsAt = $get('starts_at');
                            $endsAt = $get('ends_at');

                            if (! $startsAt || ! $endsAt) {
                                return;
                            }

                            $overlap = Booking::where('room_id', $value)
                                ->where('status', 'approved')
                                ->where(function ($query) use ($startsAt, $endsAt) {
                                    $query->whereBetween('starts_at', [$startsAt, $endsAt])
                                        ->orWhereBetween('ends_at', [$startsAt, $endsAt])
                                        ->orWhere(function ($q) use ($startsAt, $endsAt) {
                                            $q->where('starts_at', '<=', $startsAt)
                                                ->where('ends_at', '>=', $endsAt);
                                        });
                                })
                                ->exists();

                            if ($overlap) {
                                $fail('This room is already booked for the selected time slot.');
                            }
                        },
                    ]),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('starts_at')
                    ->required()
                    ->before('ends_at')
                    ->seconds(false)
                    ->disabledOn('edit'),
                DateTimePicker::make('ends_at')
                    ->required()
                    ->after('starts_at')
                    ->seconds(false)
                    ->disabledOn('edit'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
