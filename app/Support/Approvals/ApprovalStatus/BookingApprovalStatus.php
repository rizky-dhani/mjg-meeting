<?php

namespace App\Support\Approvals\ApprovalStatus;

use App\Support\Approvals\Contracts\HasApprovalStatuses;

enum BookingApprovalStatus: string implements HasApprovalStatuses
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public static function getApprovedStatuses(): array
    {
        return [self::Approved];
    }

    public static function getDeniedStatuses(): array
    {
        return [self::Rejected];
    }

    public static function getPendingStatuses(): array
    {
        return [self::Pending];
    }

    public static function getCaseLabel(self $case): string
    {
        return match ($case) {
            self::Approved => 'Approved',
            self::Pending => 'Pending',
            self::Rejected => 'Rejected',
        };
    }
}
