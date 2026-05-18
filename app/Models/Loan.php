<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'interest_rate',
        'total_due',
        'amount_paid',
        'status',
        'due_date',
        'purpose',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
