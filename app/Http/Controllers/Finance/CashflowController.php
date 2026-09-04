<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashflowEntry;
use App\Models\Estate;
use App\Models\Payment;
use App\Models\SettlementAccount;
use App\Services\Audit\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashflowController extends Controller
{
    public function index(Request $request): Response
    {
        $isSuperAdmin = (bool) $request->user()?->isSuperAdmin();
        $query = CashflowEntry::query()->with(['estate:id,name', 'settlementAccount:id,label', 'creator:id,name']);

        if (! $isSuperAdmin) {
            $query->where('created_by', $request->user()?->id);
        }

        $entries = $query->orderByDesc('occurred_on')->orderByDesc('id')->get();
        $monthEntries = $entries->filter(fn (CashflowEntry $entry): bool => $entry->occurred_on->isSameMonth(now()));
        $rentCollections = $isSuperAdmin
            ? Payment::query()->where('status', 'confirmed')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')
            : null;
        $otherRevenue = $monthEntries->where('type', 'revenue')->sum('amount');
        $expenses = $monthEntries->where('type', 'expense')->sum('amount');

        return Inertia::render('cashflow/Index', [
            'isSuperAdmin' => $isSuperAdmin,
            'scopeLabel' => $isSuperAdmin ? 'Entire portfolio' : 'Entries recorded by you',
            'summary' => [
                'rent_collections' => $rentCollections,
                'other_revenue' => $otherRevenue,
                'expenses' => $expenses,
                'net' => ($rentCollections ?? 0) + $otherRevenue - $expenses,
                'recurring' => $entries->where('is_recurring', true)->count(),
            ],
            'entries' => $entries->map(fn (CashflowEntry $entry): array => [
                'id' => $entry->id,
                'reference' => $entry->reference,
                'type' => $entry->type,
                'category' => $entry->category,
                'description' => $entry->description,
                'amount' => $entry->amount,
                'occurred_on' => $entry->occurred_on->toDateString(),
                'occurred_on_label' => $entry->occurred_on->format('d M Y'),
                'estate_id' => $entry->estate_id,
                'estate' => $entry->estate?->name,
                'settlement_account_id' => $entry->settlement_account_id,
                'account' => $entry->settlementAccount?->label,
                'counterparty' => $entry->counterparty,
                'payment_method' => $entry->payment_method,
                'external_reference' => $entry->external_reference,
                'is_recurring' => $entry->is_recurring,
                'recurrence' => $entry->recurrence,
                'notes' => $entry->notes,
                'has_receipt' => $entry->receipt_path !== null,
                'receipt_url' => $entry->receipt_path === null ? null : route('cashflow.receipt', $entry),
                'created_by' => $entry->created_by === null ? 'System' : $entry->creator->name,
            ])->values(),
            'estates' => Estate::query()->orderBy('name')->get(['id', 'name']),
            'accounts' => $isSuperAdmin
                ? SettlementAccount::query()->where('is_active', true)->orderBy('label')->get(['id', 'label'])
                : [],
            'categories' => [
                'expense' => ['Salaries & wages', 'Security', 'Caretaker', 'Rubbish collection', 'Repairs & maintenance', 'Utilities', 'Cleaning', 'Taxes & licences', 'Insurance', 'Professional fees', 'Supplies', 'Transport', 'Other expense'],
                'revenue' => ['Late fee', 'Utility recovery', 'Parking', 'Laundry', 'Damage recovery', 'Service charge', 'Other revenue'],
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, ActivityRecorder $audit): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $receipt = $request->file('receipt');
        $path = $receipt?->store('cashflow-receipts/'.now()->format('Y/m'), 'local');

        $entry = CashflowEntry::query()->create([
            ...Arr::except($data, ['receipt']),
            'reference' => $this->nextReference(),
            'settlement_account_id' => $request->user()?->isSuperAdmin() ? ($data['settlement_account_id'] ?? null) : null,
            'recurrence' => $data['is_recurring'] ? $data['recurrence'] : null,
            'receipt_path' => $path ?: null,
            'receipt_original_name' => $receipt?->getClientOriginalName(),
            'receipt_mime' => $receipt?->getMimeType(),
            'receipt_size' => $receipt?->getSize(),
            'created_by' => $request->user()?->id,
        ]);

        $audit->record($request, 'cashflow.created', "Recorded {$entry->type} {$entry->reference}: {$entry->description}.", $entry, ['amount' => $entry->amount]);

        return to_route('cashflow.index')->with('success', ucfirst($entry->type).' recorded in the cashbook.');
    }

    public function update(Request $request, CashflowEntry $cashflowEntry, ActivityRecorder $audit): RedirectResponse
    {
        $this->authorizeEntry($request, $cashflowEntry);
        $data = $request->validate($this->rules());
        $receipt = $request->file('receipt');
        $oldPath = $cashflowEntry->receipt_path;
        $path = $receipt?->store('cashflow-receipts/'.now()->format('Y/m'), 'local');

        $cashflowEntry->update([
            ...Arr::except($data, ['receipt']),
            'settlement_account_id' => $request->user()?->isSuperAdmin() ? ($data['settlement_account_id'] ?? null) : null,
            'recurrence' => $data['is_recurring'] ? $data['recurrence'] : null,
            ...($receipt === null ? [] : [
                'receipt_path' => $path ?: null,
                'receipt_original_name' => $receipt->getClientOriginalName(),
                'receipt_mime' => $receipt->getMimeType(),
                'receipt_size' => $receipt->getSize(),
            ]),
        ]);

        if ($receipt !== null && $oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        $audit->record($request, 'cashflow.updated', "Updated cashbook record {$cashflowEntry->reference}.", $cashflowEntry, ['amount' => $cashflowEntry->amount]);

        return to_route('cashflow.index')->with('success', 'Cashbook record updated.');
    }

    public function destroy(Request $request, CashflowEntry $cashflowEntry, ActivityRecorder $audit): RedirectResponse
    {
        $this->authorizeEntry($request, $cashflowEntry);
        $reference = $cashflowEntry->reference;
        $path = $cashflowEntry->receipt_path;
        $audit->record($request, 'cashflow.deleted', "Deleted cashbook record {$reference}.", $cashflowEntry, ['amount' => $cashflowEntry->amount]);
        $cashflowEntry->delete();

        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }

        return to_route('cashflow.index')->with('success', "Cashbook record {$reference} deleted.");
    }

    public function receipt(Request $request, CashflowEntry $cashflowEntry): StreamedResponse
    {
        $this->authorizeEntry($request, $cashflowEntry);

        if ($cashflowEntry->receipt_path === null || ! Storage::disk('local')->exists($cashflowEntry->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($cashflowEntry->receipt_path, $cashflowEntry->receipt_original_name ?? 'receipt');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['expense', 'revenue'])],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', 'date'],
            'estate_id' => ['nullable', 'exists:estates,id'],
            'settlement_account_id' => ['nullable', 'exists:settlement_accounts,id'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(['cash', 'mobile_money', 'bank_transfer', 'card', 'other'])],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'is_recurring' => ['required', 'boolean'],
            'recurrence' => ['nullable', 'required_if:is_recurring,true', Rule::in(['weekly', 'monthly', 'quarterly', 'annually'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    private function authorizeEntry(Request $request, CashflowEntry $entry): void
    {
        abort_unless($request->user()?->isSuperAdmin() || $entry->created_by === $request->user()?->id, 403);
    }

    private function nextReference(): string
    {
        $next = (int) CashflowEntry::query()->max('id') + 1;

        return 'CF-'.now()->format('Y').'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
