<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'route_stop_id',
        'checkin_time',
        'checkout_time',
        'proof_image_url',
        'notes',
    ];

    protected $casts = [
        'checkin_time' => 'datetime',
        'checkout_time' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(CourierAssignment::class, 'assignment_id');
    }

    public function stop()
    {
        return $this->belongsTo(RouteStop::class, 'route_stop_id');
    }
}
