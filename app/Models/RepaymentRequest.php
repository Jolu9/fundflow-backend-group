<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepaymentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'loan_id',
        'community_id',
        'amount',
        'reference_note',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
