<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_code',
        'name',
        'category',
        'unit_price',
        'sort_order',
        'stock',
        'weight',
        'unit',
        'image_url',
        'accurate_item_id',
    ];

    protected $casts = [
        'unit_price' => 'double',
        'sort_order' => 'integer',
        'stock' => 'integer',
        'weight' => 'double',
    ];
}
