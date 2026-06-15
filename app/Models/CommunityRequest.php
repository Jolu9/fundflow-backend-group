<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityRequest extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
