<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Booking;
use Filament\Actions\ViewAction;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Approvals extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Approvals';

    protected static ?string $slug = 'approvals';

    public static function canView(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('Super Admin')
            || $user->hasRole('Admin')
            || $user->hasRole('Head')
        );
    }

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

        $isDivisionScoped = $user->hasRole('Head') || $user->hasRole('Admin');

        // Head/Admin: only approvals for bookings from their own division
        if ($isDivisionScoped && $user->division_id !== null) {
            $query->whereHas('booker', fn ($uq) => $uq->where('division_id', $user->division_id));
        }

        $userRoleNames = $user->getRoleNames();

        $matchingSteps = $flow->steps()
            ->with('role', 'divisions')
            ->whereHas('role', fn ($q) => $q->whereIn('name', $userRoleNames))
            ->get();

        if ($matchingSteps->isEmpty()) {
            // No actionable step for this user: division-scoped viewers (e.g. Head
            // without a matching flow step) still see their division's in-flight
            // approvals; everyone else sees nothing.
            if (! $isDivisionScoped) {
                return $query->whereRaw('0 = 1');
            }

            return $query
                ->whereHas('approvals', fn ($aq) => $aq->where('key', $flow->name))
                ->whereDoesntHave('approvals', fn ($aq) => $aq
                    ->where('key', $flow->name)
                    ->whereIn('status', ['rejected', 'denied']))
                ->whereRaw('EXISTS (
                    SELECT 1 FROM approval_flow_steps s
                    WHERE s.approval_flow_id = ?
                      AND NOT EXISTS (
                          SELECT 1 FROM approvals a
                          WHERE a.approvable_type = ?
                            AND a.approvable_id = bookings.id
                            AND a.approval_flow_step_id = s.id
                            AND a.status = ?
                            AND a.deleted_at IS NULL
                      )
                )', [$flow->id, Booking::class, 'approved']);
        }

        $query->where(function ($q) use ($flow, $matchingSteps, $user) {
            foreach ($matchingSteps as $step) {
                $q->orWhere(function ($sq) use ($flow, $step, $user) {
                    // Scope check: skip steps the user cannot act on
                    if ($step->scope === ApprovalFlowStep::SCOPE_DEPARTMENT) {
                        if ($step->divisions->isNotEmpty() && ! $step->divisions->contains('id', $user->division_id)) {
                            $sq->whereRaw('0 = 1');

                            return;
                        }
                    }

                    // Requester scope: restrict to the user's division
                    if ($step->scope === ApprovalFlowStep::SCOPE_REQUESTER) {
                        if ($user->division_id) {
                            $sq->whereHas('user', fn ($uq) => $uq->where('division_id', $user->division_id));
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
            TextColumn::make('booking_number')
                ->label('Booking #')
                ->searchable()
                ->sortable(),
            TextColumn::make('date')
                ->sortable()
                ->state(fn (Booking $record): string => strtoupper($record->date->format('d F Y'))),
            TextColumn::make('time')
                ->label('Time')
                ->state(fn (Booking $record): string => $record->starts_at->format('H:i').' - '.$record->ends_at->format('H:i'))
                ->sortable(['starts_at', 'ends_at']),
            TextColumn::make('title')
                ->searchable()
                ->sortable()
                ->limit(30),
            TextColumn::make('room.name')
                ->sortable()
                ->searchable(),
            TextColumn::make('user.name')
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
            BookingsTable::getApproveAction(),
            BookingsTable::getRejectAction(),
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
