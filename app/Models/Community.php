<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    protected $fillable = ['name', 'description', 'created_by', 'invite_code'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'community_user')->withPivot('role')->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function joinRequests()
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
