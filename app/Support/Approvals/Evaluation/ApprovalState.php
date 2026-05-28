<?php

namespace App\Support\Approvals\Evaluation;

enum ApprovalState: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
