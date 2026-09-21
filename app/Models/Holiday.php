<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['date', 'name', 'type', 'description'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
