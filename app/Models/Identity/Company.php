<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $connection = 'medquest_users';
    protected $table = 'companies';
    protected $primaryKey = 'companyId';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'companyId',
        'name',
        'initial',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_id', 'companyId');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id', 'companyId');
    }
}
