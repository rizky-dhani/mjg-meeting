<?php

namespace App\Filament\Resources\Rooms;

use App\Filament\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Resources\Rooms\Pages\EditRoom;
use App\Filament\Resources\Rooms\Pages\ListRooms;
use App\Filament\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Resources\Rooms\Tables\RoomsTable;
use App\Models\Room;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static string | UnitEnum | null $navigationGroup = 'System Management';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/create'),
            'edit' => EditRoom::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Admin') || auth()->user()?->hasRole('Super Admin');
    }
}
