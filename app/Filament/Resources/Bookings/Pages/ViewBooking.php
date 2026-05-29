<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\ApprovalFlow;
use App\Models\Booking;
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
                                TextEntry::make('date')
                                    ->date(),
                                TextEntry::make('room.location.name')
                                    ->label('Location'),
                                TextEntry::make('starts_at')
                                    ->time(),
                                TextEntry::make('ends_at')
                                    ->time(),
                                TextEntry::make('user.name')
                                    ->label('Booked by'),
                            ]),
                    ]),
                Section::make('Approval Status')
                    ->components([
                        TextEntry::make('_approval_state')
                            ->label('Status')
                            ->badge()
                            ->state(fn(Booking $record): string => ucfirst($record->approvalState()->value))
                            ->color(fn(Booking $record): string => match ($record->approvalState()->value) {
                                'approved' => 'success',
                                'denied' => 'danger',
                                'pending' => 'warning',
                                'open' => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('_actionable_step')
                            ->label('Current Step')
                            ->state(fn(Booking $record): string => static::getActionableStepLabel($record))
                            ->visible(fn(Booking $record): bool => ! $record->isApproved() && ! $record->isDenied()),
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

    protected static function getActionableStepLabel(Booking $record): string
    {
        $step = $record->currentActionableStep();

        if ($step === null || $step->role === null) {
            return 'Waiting...';
        }

        return "Step {$step->step_order}: {$step->role->name} approval required";
    }
}
