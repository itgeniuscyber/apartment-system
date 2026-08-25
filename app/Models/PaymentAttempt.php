<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $invoice_id
 * @property int $tenant_id
 * @property int $settlement_account_id
 * @property int|null $payment_link_id
 * @property string $provider
 * @property string $payment_method
 * @property string $idempotency_key
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property string|null $provider_reference
 * @property string|null $checkout_url
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array<string, mixed>|null $metadata
 * @property int|null $initiated_by
 * @property Carbon|null $expires_at
 * @property Carbon|null $confirmed_at
 * @property-read Invoice $invoice
 * @property-read PaymentLink|null $paymentLink
 * @property-read Payment|null $payment
 */
class PaymentAttempt extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'invoice_id',
        'tenant_id',
        'settlement_account_id',
        'payment_link_id',
        'provider',
        'payment_method',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'provider_reference',
        'checkout_url',
        'failure_code',
        'failure_message',
        'metadata',
        'initiated_by',
        'expires_at',
        'confirmed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'encrypted:array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<PaymentLink, $this> */
    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
