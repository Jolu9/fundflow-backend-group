<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'community_id',
        'amount',
        'interest_rate',
        'total_due',
        'amount_paid',
        'status',
        'due_date',
        'purpose',
        'review_note',
        'penalty_amount',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
