<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $label
 * @property string $owner_name
 * @property string $bank_name
 * @property string $account_last_four
 * @property string|null $account_number
 * @property string $currency
 * @property bool $is_active
 * @property-read Collection<int, Estate> $estates
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, PaymentLink> $paymentLinks
 */
class SettlementAccount extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'label',
        'owner_name',
        'bank_name',
        'account_last_four',
        'account_number',
        'currency',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Estate, $this> */
    public function estates(): HasMany
    {
        return $this->hasMany(Estate::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<PaymentLink, $this> */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class);
    }
}
