<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string $type
 * @property string $category
 * @property string $description
 * @property int $amount
 * @property string $currency
 * @property Carbon $occurred_on
 * @property int|null $estate_id
 * @property int|null $settlement_account_id
 * @property string|null $counterparty
 * @property string $payment_method
 * @property string|null $external_reference
 * @property bool $is_recurring
 * @property string|null $recurrence
 * @property string|null $notes
 * @property string|null $receipt_path
 * @property string|null $receipt_original_name
 * @property string|null $receipt_mime
 * @property int|null $receipt_size
 * @property int|null $created_by
 * @property-read Estate|null $estate
 * @property-read SettlementAccount|null $settlementAccount
 * @property-read User|null $creator
 */
#[Fillable([
    'reference', 'type', 'category', 'description', 'amount', 'currency', 'occurred_on',
    'estate_id', 'settlement_account_id', 'counterparty', 'payment_method',
    'external_reference', 'is_recurring', 'recurrence', 'notes', 'receipt_path',
    'receipt_original_name', 'receipt_mime', 'receipt_size', 'created_by',
])]
class CashflowEntry extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_on' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /** @return BelongsTo<Estate, $this> */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /** @return BelongsTo<SettlementAccount, $this> */
    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(SettlementAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
