<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStatus extends Model
{
    public const CATEGORIES = ['prospecting', 'active_lead', 'won', 'lost'];

    public const CATEGORY_LABELS = [
        'prospecting' => 'Prospecting',
        'active_lead' => 'Active Lead (In negotiation)',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    protected $fillable = [
        'name', 'slug', 'category', 'description', 'badge_color', 'order_position', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'order_position' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'status', 'slug');
    }
}
