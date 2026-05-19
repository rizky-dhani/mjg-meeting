<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->disabledOn('edit'),
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
