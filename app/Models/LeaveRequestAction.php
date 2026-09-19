<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequestAction extends Model
{
    protected $fillable = ['leave_request_id', 'action', 'actor_user_id', 'note'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
