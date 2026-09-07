<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'module_slug',
        'action',
        'name',
        'description',
    ];

    /**
     * Roles relationship.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
