<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance')
                    ->schema([
                        Select::make('user_id')
                            ->label('Staff Member')
                            ->placeholder('Select a staff member (or fill guest details below)')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->nullable(),
                        TextInput::make('guest_name')
                            ->label('Guest Name')
                            ->placeholder('Name of the guest (if not a staff member)')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('guest_from')
                            ->label('Guest From')
                            ->placeholder('e.g., Acme Corp')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('guest_designation')
                            ->label('Guest Designation')
                            ->placeholder('e.g., Vendor PIC')
                            ->maxLength(255)
                            ->nullable(),
                    ]),
            ]);
    }
}
