<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies';

    protected $fillable = [
        'company_id',
        'name',
        'initial',
    ];

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
