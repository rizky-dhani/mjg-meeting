<?php

namespace App\Filament\Resources\Divisions\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DivisionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Division Info')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('initial')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
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
