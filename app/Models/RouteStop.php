<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'store_name',
        'address',
        'latitude',
        'longitude',
        'sequence_order',
        'radius_tolerance_meters',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'sequence_order' => 'integer',
        'radius_tolerance_meters' => 'integer',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function visits()
    {
        return $this->hasMany(StoreVisit::class, 'route_stop_id');
    }
}
