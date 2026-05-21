<?php

namespace App\Support\Approvals\Enums;

enum ApprovalState: string
{
    case APPROVED = 'approved';
    case DENIED = 'denied';
    case PENDING = 'pending';
    case OPEN = 'open';
}
