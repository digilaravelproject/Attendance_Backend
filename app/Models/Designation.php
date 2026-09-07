<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hierarchy_level',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'employee');
    }
}
