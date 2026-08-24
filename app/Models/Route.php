<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_code',
        'route_name',
        'area_name',
        'path_polyline',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stops()
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('sequence_order', 'asc');
    }

    public function assignments()
    {
        return $this->hasMany(CourierAssignment::class, 'route_id');
    }
}
