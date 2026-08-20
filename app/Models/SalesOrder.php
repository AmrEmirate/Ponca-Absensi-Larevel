<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no',
        'customer_id',
        'user_id',
        'sales_agent_name',
        'order_date',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total_amount',
        'payment_method',
        'receipt_url',
        'sync_status',
        'accurate_invoice_no',
        'sync_error_message',
        'is_verified',
        'verified_at',
        'verified_by_name',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'verified_at' => 'datetime',
        'subtotal' => 'double',
        'discount_value' => 'double',
        'discount_amount' => 'double',
        'total_amount' => 'double',
        'is_verified' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
