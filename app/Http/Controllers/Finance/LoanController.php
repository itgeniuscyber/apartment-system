<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Tenant;
use App\Services\Audit\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function index(): Response
    {
        $loans = Loan::query()->with(['payments' => fn ($query) => $query->orderByDesc('paid_on')->orderByDesc('id'), 'tenant:id,first_name,last_name'])->latest()->get();

        return Inertia::render('loans/Index', [
            'summary' => [
                'borrowed_balance' => $loans->where('direction', 'borrowed')->sum(fn (Loan $loan): int => $loan->balance()),
                'issued_balance' => $loans->where('direction', 'issued')->sum(fn (Loan $loan): int => $loan->balance()),
                'paid_total' => $loans->sum(fn (Loan $loan): int => $loan->amountPaid()),
                'due_soon' => $loans->filter(fn (Loan $loan): bool => $loan->status === 'active' && $loan->next_due_date !== null && $loan->next_due_date->between(now()->startOfDay(), now()->addDays(7)->endOfDay()))->count(),
                'overdue' => $loans->filter(fn (Loan $loan): bool => $loan->status === 'active' && $loan->next_due_date?->isPast())->count(),
            ],
            'loans' => $loans->map(fn (Loan $loan): array => $this->loanData($loan))->values(),
            'tenants' => Tenant::query()->orderBy('first_name')->orderBy('last_name')->get()->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->full_name,
            ]),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, ActivityRecorder $audit): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $loan = Loan::query()->create([
            ...$data,
            'reference' => $this->nextReference(),
            'created_by' => $request->user()?->id,
        ]);

        $audit->record($request, 'loan.created', "Created {$loan->direction} loan {$loan->reference}: {$loan->name}.", $loan, ['principal' => $loan->principal_amount]);

        return to_route('loans.index')->with('success', 'Loan added with its repayment and reminder schedule.');
    }

    public function update(Request $request, Loan $loan, ActivityRecorder $audit): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $paid = $loan->payments()->sum('amount');

        if ($data['total_payable'] < $paid) {
            throw ValidationException::withMessages(['total_payable' => 'Total payable cannot be less than payments already recorded.']);
        }

        $loan->update([...$data, 'last_reminded_on' => null]);
        $this->settleIfComplete($loan);
        $audit->record($request, 'loan.updated', "Updated loan {$loan->reference}.", $loan);

        return to_route('loans.index')->with('success', 'Loan details and reminder schedule updated.');
    }

    public function recordPayment(Request $request, Loan $loan, ActivityRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'mobile_money', 'bank_transfer', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:paid_on'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $remaining = $loan->total_payable - $loan->payments()->sum('amount');

        if ($data['amount'] > $remaining) {
            throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the remaining loan balance.']);
        }

        $payment = $loan->payments()->create([
            ...$data,
            'recorded_by' => $request->user()?->id,
        ]);

        $loan->update(['next_due_date' => $data['next_due_date'], 'last_reminded_on' => null]);
        $this->settleIfComplete($loan);
        $audit->record($request, 'loan.payment_recorded', "Recorded a payment for loan {$loan->reference}.", $loan, ['amount' => $payment->amount]);

        return to_route('loans.index')->with('success', $loan->direction === 'borrowed' ? 'Loan repayment recorded.' : 'Loan receipt recorded.');
    }

    public function destroyPayment(Request $request, Loan $loan, LoanPayment $loanPayment, ActivityRecorder $audit): RedirectResponse
    {
        abort_unless($loanPayment->loan_id === $loan->id, 404);
        $amount = $loanPayment->amount;
        $loanPayment->delete();

        if ($loan->status === 'settled') {
            $loan->update(['status' => 'active']);
        }

        $audit->record($request, 'loan.payment_deleted', "Removed a payment correction from loan {$loan->reference}.", $loan, ['amount' => $amount]);

        return to_route('loans.index')->with('success', 'Loan payment entry removed and balance recalculated.');
    }

    public function destroy(Request $request, Loan $loan, ActivityRecorder $audit): RedirectResponse
    {
        $reference = $loan->reference;
        $audit->record($request, 'loan.deleted', "Deleted loan {$reference} and its payment history.", $loan);
        $loan->delete();

        return to_route('loans.index')->with('success', "Loan {$reference} deleted.");
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['borrowed', 'issued'])],
            'name' => ['required', 'string', 'max:255'],
            'counterparty_name' => ['required', 'string', 'max:255'],
            'counterparty_phone' => ['nullable', 'string', 'max:30'],
            'counterparty_email' => ['nullable', 'email', 'max:255'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'principal_amount' => ['required', 'integer', 'min:1'],
            'total_payable' => ['required', 'integer', 'gte:principal_amount'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'installment_amount' => ['nullable', 'integer', 'min:1'],
            'repayment_frequency' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'quarterly', 'custom'])],
            'start_date' => ['required', 'date'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'maturity_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:60'],
            'status' => ['required', Rule::in(['active', 'paused', 'settled', 'written_off'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, mixed> */
    private function loanData(Loan $loan): array
    {
        $paid = $loan->amountPaid();
        $balance = $loan->balance();
        $effectiveStatus = $loan->status === 'active' && $loan->next_due_date?->isPast() ? 'overdue' : $loan->status;

        return [
            'id' => $loan->id,
            'reference' => $loan->reference,
            'direction' => $loan->direction,
            'name' => $loan->name,
            'counterparty_name' => $loan->counterparty_name,
            'counterparty_phone' => $loan->counterparty_phone,
            'counterparty_email' => $loan->counterparty_email,
            'tenant_id' => $loan->tenant_id,
            'tenant' => $loan->tenant?->full_name,
            'principal_amount' => $loan->principal_amount,
            'total_payable' => $loan->total_payable,
            'amount_paid' => $paid,
            'balance' => $balance,
            'progress' => $loan->total_payable > 0 ? min(100, (int) round(($paid / $loan->total_payable) * 100)) : 0,
            'interest_rate' => $loan->interest_rate,
            'installment_amount' => $loan->installment_amount,
            'repayment_frequency' => $loan->repayment_frequency,
            'start_date' => $loan->start_date->toDateString(),
            'next_due_date' => $loan->next_due_date?->toDateString(),
            'next_due_label' => $loan->next_due_date?->format('d M Y'),
            'maturity_date' => $loan->maturity_date?->toDateString(),
            'reminder_days_before' => $loan->reminder_days_before,
            'status' => $loan->status,
            'effective_status' => $effectiveStatus,
            'notes' => $loan->notes,
            'payments' => $loan->payments->map(fn (LoanPayment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'paid_on' => $payment->paid_on->toDateString(),
                'paid_on_label' => $payment->paid_on->format('d M Y'),
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
            ])->values(),
        ];
    }

    private function settleIfComplete(Loan $loan): void
    {
        if ($loan->payments()->sum('amount') >= $loan->total_payable) {
            $loan->update(['status' => 'settled', 'next_due_date' => null]);
        }
    }

    private function nextReference(): string
    {
        $next = (int) Loan::query()->max('id') + 1;

        return 'LN-'.now()->format('Y').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
