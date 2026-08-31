<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ApprovalFlowStep extends Model
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_REQUESTER = 'requester';

    public const SCOPES = [
        self::SCOPE_ALL,
        self::SCOPE_DEPARTMENT,
        self::SCOPE_REQUESTER,
    ];

    protected $fillable = [
        'approval_flow_id',
        'role_id',
        'step_order',
        'scope',
    ];

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class);
    }
}
