<?php

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use SoftDeletes;

    protected $connection = 'medquest_users';
    protected $table = 'designations';
    protected $primaryKey = 'designationId';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'designationId',
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'designation_id', 'designationId');
    }
}
