<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Filament\Components\ApprovalActions;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Meeting Details')
                    ->components([
                        TextEntry::make('title')
                            ->weight(FontWeight::Bold)
                            ->size('lg'),
                        TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),
                        Group::make()
                            ->columns(2)
                            ->components([
                                TextEntry::make('room.name')
                                    ->label('Room'),
                                TextEntry::make('room.location.name')
                                    ->label('Location'),
                                TextEntry::make('starts_at')
                                    ->dateTime('M d, Y H:i'),
                                TextEntry::make('ends_at')
                                    ->dateTime('M d, Y H:i'),
                                TextEntry::make('user.name')
                                    ->label('Booked by'),
                            ]),
                    ]),
                Section::make('Approval')
                    ->components([
                        ApprovalActions::make('booking_approval'),
                    ]),
                Section::make('QR Code')
                    ->visible(fn(Booking $record): bool => $record->isApproved())
                    ->components([
                        ImageEntry::make('qr_code')
                            ->label('Scan to check in')
                            ->size(200)
                            ->simpleLightbox()
                            ->url(fn(Booking $record): string =>
                                'data:image/png;base64,' . base64_encode(
                                    \Milon\Barcode\Facades\DNS2DFacade::getBarcodePNG($record->qr_code, 'QRCODE', 8, 8)
                                )
                            )
                            ->extraImgAttributes(['class' => 'mx-auto']),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
