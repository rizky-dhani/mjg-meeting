<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Booking;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Approvals extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Approvals';

    protected static ?string $slug = 'approvals';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getPendingApprovalsQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    protected static function getPendingApprovalsQuery(): Builder
    {
        $user = Auth::user();

        $query = Booking::query()->with('approvals');

        $flow = ApprovalFlow::where('model_type', Booking::class)->first();

        if (! $flow) {
            return $query->whereRaw('0 = 1');
        }

        $userRoleNames = $user->getRoleNames();

        $matchingSteps = $flow->steps()
            ->with('role')
            ->whereHas('role', fn ($q) => $q->whereIn('name', $userRoleNames))
            ->get();

        if ($matchingSteps->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        $query->where(function ($q) use ($flow, $matchingSteps, $user) {
            foreach ($matchingSteps as $step) {
                $q->orWhere(function ($sq) use ($flow, $step, $user) {
                    // Scope check: skip steps the user cannot act on
                    if ($step->scope === ApprovalFlowStep::SCOPE_DEPARTMENT) {
                        if ($step->department_id && $user->department_id !== $step->department_id) {
                            $sq->whereRaw('0 = 1');

                            return;
                        }
                    }

                    // Requester scope: restrict to the user's department
                    if ($step->scope === ApprovalFlowStep::SCOPE_REQUESTER) {
                        if ($user->department_id) {
                            $sq->whereHas('user', fn ($uq) => $uq->where('department_id', $user->department_id));
                        }
                    }

                    // All previous steps must be approved
                    $prevSteps = $flow->steps()
                        ->where('step_order', '<', $step->step_order)
                        ->with('role')
                        ->get();

                    foreach ($prevSteps as $prevStep) {
                        $sq->whereHas('approvals', function ($aq) use ($prevStep) {
                            $aq->where('approval_flow_step_id', $prevStep->id)
                                ->where('status', 'approved');
                        });
                    }

                    // This step must NOT yet be approved
                    $sq->whereDoesntHave('approvals', function ($aq) use ($step) {
                        $aq->where('approval_flow_step_id', $step->id)
                            ->where('status', 'approved');
                    });

                    // Not denied anywhere in the flow
                    $sq->whereDoesntHave('approvals', function ($aq) use ($flow) {
                        $aq->where('key', $flow->name)
                            ->whereIn('status', ['rejected', 'denied']);
                    });
                });
            }
        });

        return $query;
    }

    protected function getTableQuery(): Builder
    {
        return static::getPendingApprovalsQuery();
    }

    protected function getTableColumns(): array
    {
        return [
            \Filament\Tables\Columns\TextColumn::make('booking_number')
                ->label('Booking #')
                ->searchable()
                ->sortable(),
            \Filament\Tables\Columns\TextColumn::make('date')
                ->sortable()
                ->state(fn (Booking $record): string => strtoupper($record->date->format('d F Y'))),
            \Filament\Tables\Columns\TextColumn::make('time')
                ->label('Time')
                ->state(fn (Booking $record): string => $record->starts_at->format('H:i') . ' - ' . $record->ends_at->format('H:i'))
                ->sortable(['starts_at', 'ends_at']),
            \Filament\Tables\Columns\TextColumn::make('title')
                ->searchable()
                ->sortable()
                ->limit(30),
            \Filament\Tables\Columns\TextColumn::make('room.name')
                ->sortable()
                ->searchable(),
            \Filament\Tables\Columns\TextColumn::make('user.name')
                ->sortable()
                ->searchable()
                ->label('Booked by'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make()
                ->url(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record])),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Booking $record): bool => BookingsTable::canApproveStep($record))
                ->requiresConfirmation()
                ->action(function (Booking $record) {
                    BookingsTable::processApproval($record, 'approved');
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Booking $record): bool => BookingsTable::canApproveStep($record))
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Reason for rejection')
                        ->required(),
                ])
                ->action(function (Booking $record, array $data) {
                    BookingsTable::processApproval($record, 'rejected', $data['reason'] ?? null);

                    $record->user->notify(
                        new \App\Notifications\BookingRejected($record, $data['reason'] ?? null)
                    );
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]));
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'created_at';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
