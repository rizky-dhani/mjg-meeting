<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalFlow extends Model
{
    protected $fillable = [
        'name',
        'model_type',
        'description',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalFlowStep::class)->orderBy('step_order');
    }

    public function getStepCountAttribute(): int
    {
        return $this->steps()->count();
    }
}
