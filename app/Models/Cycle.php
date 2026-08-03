<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{
    protected $fillable = [
        'community_id',
        'cycle_number',
        'recipient_id',
        'pot_amount',
        'status',
        'payout_date',
        'notes',
    ];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
