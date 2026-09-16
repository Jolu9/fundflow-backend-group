<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'title',
        'subtitle',
        'route',
        'amount',
        'notifiable_type',
        'notifiable_id',
        'dismissed_at'
    ];

    protected $casts = [
        'dismissed_at' => 'datetime',
    ];
}
