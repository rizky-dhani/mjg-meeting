<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Group;
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
                        Group::make()
                            ->columns(3)
                            ->components([
                                TextEntry::make('title')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg')
                                    ->columnSpan(1),
                                TextEntry::make('description')
                                    ->markdown()
                                    ->columnSpan(2),
                            ]),
                        Group::make()
                            ->columns(3)
                            ->components([
                                TextEntry::make('room.name')
                                    ->label('Room'),
                                TextEntry::make('date')
                                    ->label('Date')
                                    ->state(fn(Booking $record): string => strtoupper($record->date->format('d F Y'))),
                                TextEntry::make('room.location.name')
                                    ->label('Location'),
                            ]),
                        Group::make()
                            ->columns(3)
                            ->components([
                                TextEntry::make('starts_at')
                                    ->label('Starts At')
                                    ->time(),
                                TextEntry::make('ends_at')
                                    ->label('Ends At')
                                    ->time(),
                                TextEntry::make('user.name')
                                    ->label('Booked by'),
                            ]),
                    ]),

                Section::make('Approval Status')
                    ->components(array_merge(
                        [
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
                        ],
                        $this->getApprovalStepsComponents(),
                    )),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('show_qr')
                ->label('Show QR Code')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->visible(fn (Booking $record): bool => filled($record->qr_code))
                ->url(fn (Booking $record): string => route('booking.qr', $record->qr_token))
                ->openUrlInNewTab(),

            BookingsTable::getApproveAction(),
            BookingsTable::getRejectAction(),
        ];
    }

    protected function getApprovalStepsComponents(): array
    {
        /** @var Booking $record */
        $record = $this->getRecord();
        $record->loadMissing('approvals.approver');
        $flow = $record->approvalFlow();

        if ($flow === null || $flow->steps->isEmpty()) {
            return [];
        }

        $components = [];

        foreach ($flow->steps as $step) {
            $approval = $record->approvals
                ->where('approval_flow_step_id', $step->id)
                ->where('key', $flow->name)
                ->first();

            $isRejected = $approval && in_array($approval->getRawOriginal('status'), ['denied', 'rejected'], true);
            $isApproved = $approval && $approval->getRawOriginal('status') === 'approved';

            $components[] = TextEntry::make("_step_{$step->id}_header")
                ->label("Step {$step->step_order}")
                ->weight(FontWeight::Bold);

            $components[] = Group::make()
                ->columns(3)
                ->components([
                    TextEntry::make("_step_{$step->id}_approver_name")
                        ->label('Approver')
                        ->state($approval?->approver?->name ?? $this->getEligibleApproverName($record, $step)),
                    TextEntry::make("_step_{$step->id}_status")
                        ->label('Status')
                        ->badge()
                        ->state(match (true) {
                            $isApproved => 'Approved',
                            $isRejected => 'Rejected',
                            $approval !== null => ucfirst($approval->status),
                            default => 'Waiting',
                        })
                        ->color(match (true) {
                            $isApproved => 'success',
                            $isRejected => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make("_step_{$step->id}_at")
                        ->label(
                            match (true) {
                                $isRejected => 'Rejected At',
                                $isApproved => 'Approved At',
                                default => '',
                            }
                        )
                        ->state($approval !== null ? $approval->created_at->format('d M Y H:i') : '-')
                        ->hidden(fn () => ! $isApproved && ! $isRejected),
                ]);

            if ($isRejected) {
                $components[] = TextEntry::make("_step_{$step->id}_reason")
                    ->label('Rejection Reason')
                    ->state($approval->reason ?? 'No reason provided')
                    ->columnSpanFull();
            }
        }

        return $components;
    }

    protected function getEligibleApproverName(Booking $record, \App\Models\ApprovalFlowStep $step): string
    {
        $roleName = $step->role?->name;

        if ($roleName === null) {
            return 'No approver assigned';
        }

        $query = User::role($roleName);

        if ($step->scope === 'department' && $step->department_id !== null) {
            $query->where('department_id', $step->department_id);
        }

        if ($step->scope === 'requester') {
            $requesterDeptId = $record->user?->department_id;
            if ($requesterDeptId === null) {
                return 'No eligible approver';
            }
            $query->where('department_id', $requesterDeptId);
        }

        $user = $query->first();

        return $user?->name ?? "{$roleName} (no user)";
    }
}
