<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteDeviation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'latitude',
        'longitude',
        'distance_deviation_meters',
        'recorded_at',
        'is_resolved',
        'created_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_deviation_meters' => 'float',
        'recorded_at' => 'datetime',
        'is_resolved' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(CourierAssignment::class, 'assignment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
