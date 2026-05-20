<?php

namespace App\Filament\Resources\Attendances\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance')
                    ->schema([
                        //
                    ]),
            ]);
    }
}