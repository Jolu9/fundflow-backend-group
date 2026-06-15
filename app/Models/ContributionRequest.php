<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContributionRequest extends Model
{
    protected $fillable = ['user_id', 'community_id', 'amount', 'reference_note', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
