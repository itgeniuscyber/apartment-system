<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property Carbon|null $portal_invited_at
 * @property Carbon|null $portal_activated_at
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $alternative_phone
 * @property string|null $identity_document_type
 * @property string|null $identity_document_number
 * @property string|null $identity_document_last_four
 * @property string|null $identity_document_path
 * @property string|null $identity_document_original_name
 * @property string|null $identity_document_mime
 * @property int|null $identity_document_size
 * @property Carbon|null $identity_document_uploaded_at
 * @property Carbon|null $identity_verified_at
 * @property int|null $identity_verified_by
 * @property string|null $next_of_kin_name
 * @property string|null $next_of_kin_relationship
 * @property string|null $next_of_kin_phone
 * @property string|null $next_of_kin_email
 * @property string|null $next_of_kin_address
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string $status
 * @property string $preferred_channel
 * @property string|null $notes
 * @property int $leases_count
 * @property int $invoices_count
 * @property int $payments_count
 * @property int $payment_links_count
 * @property int $payment_attempts_count
 * @property-read string $full_name
 * @property-read User|null $user
 * @property-read User|null $identityVerifier
 * @property-read Collection<int, Lease> $leases
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, PaymentLink> $paymentLinks
 * @property-read Collection<int, PaymentAttempt> $paymentAttempts
 * @property-read Collection<int, MaintenanceRequest> $maintenanceRequests
 * @property-read Collection<int, NoticeRecipient> $noticeRecipients
 * @property-read Collection<int, MessageThread> $messageThreads
 * @property-read Collection<int, CommunicationDelivery> $communicationDeliveries
 * @property-read Collection<int, TenantNotificationEvent> $notificationEvents
 * @property-read Lease|null $activeLease
 */
class Tenant extends Model
{
    use Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'portal_invited_at',
        'portal_activated_at',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternative_phone',
        'identity_document_type',
        'identity_document_number',
        'identity_document_last_four',
        'identity_document_path',
        'identity_document_original_name',
        'identity_document_mime',
        'identity_document_size',
        'identity_document_uploaded_at',
        'identity_verified_at',
        'identity_verified_by',
        'next_of_kin_name',
        'next_of_kin_relationship',
        'next_of_kin_phone',
        'next_of_kin_email',
        'next_of_kin_address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
        'preferred_channel',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'identity_document_number' => 'encrypted',
            'portal_invited_at' => 'datetime',
            'portal_activated_at' => 'datetime',
            'identity_document_uploaded_at' => 'datetime',
            'identity_verified_at' => 'datetime',
        ];
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function identityVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_verified_by');
    }

    /** @return HasMany<Lease, $this> */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class)->orderByDesc('start_date');
    }

    /** @return HasOne<Lease, $this> */
    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)->where('status', 'active')->latestOfMany('start_date');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('due_date');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    /** @return HasMany<PaymentLink, $this> */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class)->latest();
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->latest();
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)->latest();
    }

    /** @return HasMany<NoticeRecipient, $this> */
    public function noticeRecipients(): HasMany
    {
        return $this->hasMany(NoticeRecipient::class)->latest();
    }

    /** @return HasMany<MessageThread, $this> */
    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class)->latest('last_message_at');
    }

    /** @return HasMany<CommunicationDelivery, $this> */
    public function communicationDeliveries(): HasMany
    {
        return $this->hasMany(CommunicationDelivery::class)->latest();
    }

    /** @return HasMany<TenantNotificationEvent, $this> */
    public function notificationEvents(): HasMany
    {
        return $this->hasMany(TenantNotificationEvent::class)->latest('scheduled_for');
    }
}
