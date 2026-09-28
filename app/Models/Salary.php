<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'salary_month', 'gross_earnings', 'total_deductions', 'net_payable',
        'earnings', 'deductions', 'attendance_summary', 'payment_date', 'payment_mode',
        'bank_name', 'account_upi_address', 'remarks', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salary_month' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'gross_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'earnings' => 'array',
            'deductions' => 'array',
            'attendance_summary' => 'array',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
