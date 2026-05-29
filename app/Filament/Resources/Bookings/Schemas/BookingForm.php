<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\ApprovalFlow;
use App\Models\Booking;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Get;
use Filament\Schemas\Components\Section;
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
                            ->disabledOn('edit')
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $date = $get('date');
                                    $startsAt = $get('starts_at');
                                    $endsAt = $get('ends_at');

                                    if (! $date || ! $startsAt || ! $endsAt) {
                                        return;
                                    }

                                    $flow = ApprovalFlow::where('model_type', Booking::class)->first();
                                    $flowName = $flow?->name ?? 'booking_approval';

                                    $overlap = Booking::where('room_id', $value)
                                        ->where('date', $date)
                                        ->where(function ($q) use ($flowName) {
                                            $q->whereDoesntHave('approvals', function ($q2) use ($flowName) {
                                                $q2->where('key', $flowName)
                                                   ->whereIn('status', ['rejected', 'denied']);
                                            });
                                        })
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
                            ->default(now()),
                        TimePicker::make('starts_at')
                            ->required()
                            ->before('ends_at')
                            ->seconds(false)
                            ->format('H:i')
                            ->native(false)
                            ->displayFormat('H:i')
                            ->suffixIcon(Heroicon::Clock)
                            ->default('00:00')
                            ->disabledOn('edit'),
                        TimePicker::make('ends_at')
                            ->required()
                            ->after('starts_at')
                            ->seconds(false)
                            ->format('H:i')
                            ->native(false)
                            ->displayFormat('H:i')
                            ->suffixIcon(Heroicon::Clock)
                            ->default('00:00')
                            ->disabledOn('edit'),
                    ]),
            ]);
    }
}
