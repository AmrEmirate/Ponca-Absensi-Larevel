<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccurateSyncDataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'dataset_name',
        'record_count',
        'status',
        'last_sync',
    ];

    protected $casts = [
        'record_count' => 'integer',
    ];
}
