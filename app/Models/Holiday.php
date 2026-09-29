<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['date', 'name', 'type', 'location', 'repeat_every_year', 'description'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'repeat_every_year' => 'boolean',
        ];
    }
}
