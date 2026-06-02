<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Booking;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
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
                        $sq->whereHas('approvals', function ($aq) use ($flow, $prevStep) {
                            $aq->where('key', $flow->name)
                                ->where('approval_by', $prevStep->role->name)
                                ->where('status', 'approved');
                        });
                    }

                    // This step must NOT yet be approved
                    $sq->whereDoesntHave('approvals', function ($aq) use ($flow, $step) {
                        $aq->where('key', $flow->name)
                            ->where('approval_by', $step->role->name)
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
        ];
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
