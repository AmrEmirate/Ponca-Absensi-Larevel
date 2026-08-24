<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierLocation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'latitude',
        'longitude',
        'speed',
        'recorded_at',
        'synced_at',
        'created_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'recorded_at' => 'datetime',
        'synced_at' => 'datetime',
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
