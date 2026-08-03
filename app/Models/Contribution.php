<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Contribution extends Model
{
    protected $fillable = ['user_id', 'recorded_by', 'amount', 'contribution_date', 'notes', 'community_id'];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
