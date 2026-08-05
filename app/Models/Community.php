<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    protected $fillable = ['name', 'description', 'created_by', 'invite_code', 'contribution_amount', 'chilimba_enabled'];

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

    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    public function cycles()
    {
        return $this->hasMany(Cycle::class);
    }

    public function currentFund()
    {
        $totalContributed = $this->contributions->sum('amount');
        $totalDisbursed = $this->loans->whereNotIn('status', ['pending', 'rejected'])->sum('amount');
        $totalRepaid = $this->loans->sum('amount_paid');
        $totalCommittedToCycles = $this->cycles()->whereIn('status', ['pending', 'active', 'completed'])->sum('pot_amount');

        return max(0, $totalContributed + $totalRepaid - $totalDisbursed - $totalCommittedToCycles);
    }
}
