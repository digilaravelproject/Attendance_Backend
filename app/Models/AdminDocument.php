<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'original_name',
        'file_name',
        'file_path',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
