<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccurateConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'api_token',
        'db_id',
        'auto_sync',
        'sync_interval_minutes',
        'last_successful_sync',
    ];

    protected $casts = [
        'auto_sync' => 'boolean',
        'sync_interval_minutes' => 'integer',
    ];
}
