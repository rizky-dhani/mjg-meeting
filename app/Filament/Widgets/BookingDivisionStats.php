<?php

namespace App\Filament\Widgets;

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

        $bookings = Booking::whereIn('user_id', $departmentUserIds)
            ->with('approvals')
            ->get();

        return [
            Stat::make('Total', $bookings->count())
                ->description('All bookings in your division')
                ->color('info'),
            Stat::make('Approved', $bookings->filter(fn (Booking $b): bool => $b->isApproved())->count())
                ->description('Approved bookings')
                ->color('success'),
            Stat::make('Pending', $bookings->filter(fn (Booking $b): bool => $b->isPending())->count())
                ->description('Awaiting approval')
                ->color('warning'),
            Stat::make('Open', $bookings->filter(fn (Booking $b): bool => $b->isOpen())->count())
                ->description('Not yet submitted')
                ->color('gray'),
            Stat::make('Denied', $bookings->filter(fn (Booking $b): bool => $b->isDenied())->count())
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
