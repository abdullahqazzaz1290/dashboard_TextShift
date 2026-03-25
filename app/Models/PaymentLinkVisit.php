<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class PaymentLinkVisit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'payment_link_id',
        'ip_address',
        'user_agent',
        'referrer',
        'query_string',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }
}
