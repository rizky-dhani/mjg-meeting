<?php

namespace App\Filament\Widgets;

use App\Models\ApprovalFlow;
use App\Models\Booking;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingDivisionStats extends BaseWidget
{
    protected static ?int $sort = 2;
    protected function getColumns(): int | array | null
    {
        return 5;
    }
    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user->department_id) {
            return $this->emptyStats();
        }

        $departmentUserIds = User::where('department_id', $user->department_id)
            ->pluck('id');

        $flow = ApprovalFlow::where('model_type', Booking::class)->first();
        $flowName = $flow?->name ?? 'booking_approval';

        $baseQuery = Booking::whereIn('user_id', $departmentUserIds);

        $total = (clone $baseQuery)->count();

        $approved = (clone $baseQuery)
            ->whereHas('approvals', fn ($q) => $q
                ->where('key', $flowName)
                ->where('status', 'approved'))
            ->count();

        $denied = (clone $baseQuery)
            ->whereHas('approvals', fn ($q) => $q
                ->where('key', $flowName)
                ->whereIn('status', ['rejected', 'denied']))
            ->count();

        $open = (clone $baseQuery)
            ->whereDoesntHave('approvals', fn ($q) => $q
                ->where('key', $flowName))
            ->count();

        $pending = $total - $approved - $denied - $open;

        return [
            Stat::make('Total', $total)
                ->description('All bookings in your division')
                ->color('info'),
            Stat::make('Approved', $approved)
                ->description('Approved bookings')
                ->color('success'),
            Stat::make('Pending', $pending)
                ->description('Awaiting approval')
                ->color('warning'),
            Stat::make('Open', $open)
                ->description('Not yet submitted')
                ->color('gray'),
            Stat::make('Denied', $denied)
                ->description('Rejected bookings')
                ->color('danger'),
        ];
    }

    private function emptyStats(): array
    {
        return [
            Stat::make('Total', 0)->description('No division assigned'),
            Stat::make('Approved', 0),
            Stat::make('Pending', 0),
            Stat::make('Open', 0),
            Stat::make('Denied', 0),
        ];
    }
}
