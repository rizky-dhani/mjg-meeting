<?php

namespace App\Support\Approvals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Approval extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'approvable_type',
        'approvable_id',
        'approver_id',
        'approver_type',
        'approval_by',
        'approval_flow_step_id',
        'status',
    ];

    public function approver(): MorphTo
    {
        return $this->morphTo('approver');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo('approvable');
    }
}
