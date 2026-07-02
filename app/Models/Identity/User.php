<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected $connection = 'medquest_users';
    protected $table = 'users';
    protected $primaryKey = 'userId';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'userId',
        'company_id',
        'employee_code',
        'name',
        'email',
        'initial',
        'department_id',
        'designation_id',
        'is_active',
        'email_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'companyId');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'departmentId');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id', 'designationId');
    }
}
