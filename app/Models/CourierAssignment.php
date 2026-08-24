<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'route_id',
        'assignment_date',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'assignment_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function locations()
    {
        return $this->hasMany(CourierLocation::class, 'assignment_id');
    }

    public function deviations()
    {
        return $this->hasMany(RouteDeviation::class, 'assignment_id');
    }

    public function visits()
    {
        return $this->hasMany(StoreVisit::class, 'assignment_id');
    }
}
