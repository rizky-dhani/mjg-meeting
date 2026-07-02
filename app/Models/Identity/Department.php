<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use SoftDeletes;

    protected $connection = 'medquest_users';
    protected $table = 'departments';
    protected $primaryKey = 'departmentId';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'departmentId',
        'company_id',
        'name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'companyId');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id', 'departmentId');
    }
}
