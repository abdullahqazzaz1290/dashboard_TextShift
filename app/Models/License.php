<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'license_code',
        'device_id',
        'copies_count',
        'plan_months',
        'starts_at',
        'expires_at',
        'status',
        'payment_status',
        'amount',
        'unit_price',
        'currency',
        'paid_at',
        'notes',
        'delivery_notes',
        'jsx_package_path',
        'jsxbin_package_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'copies_count' => 'integer',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class);
    }
}
