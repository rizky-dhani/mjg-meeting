<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Location Info')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Address')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Textarea::make('address')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ]),
                Section::make('Details')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
