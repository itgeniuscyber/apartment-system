<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitRentRate;
use App\Services\Audit\ActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitRateController extends Controller
{
    public function store(Request $request, Unit $unit, ActivityRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'effective_from' => ['required', 'date', 'after_or_equal:'.now()->startOfMonth()->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $effectiveFrom = CarbonImmutable::parse((string) $data['effective_from'])->startOfDay();

        if ($effectiveFrom->day !== 1) {
            throw ValidationException::withMessages([
                'effective_from' => 'Standard rent changes must start on the first day of a month.',
            ]);
        }

        if ($unit->rentRates()->whereDate('effective_from', $effectiveFrom)->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => 'This unit already has a standard rate starting on that date.',
            ]);
        }

        $rate = DB::transaction(function () use ($unit, $data, $effectiveFrom, $request): UnitRentRate {
            $rate = $unit->rentRates()->create([
                'amount' => $data['amount'],
                'effective_from' => $effectiveFrom,
                'source' => 'admin_revision',
                'notes' => $data['notes'],
                'created_by' => $request->user()?->id,
            ]);

            $rates = $unit->rentRates()->reorder()->orderBy('effective_from')->orderBy('id')->get();

            foreach ($rates as $index => $item) {
                $next = $rates->get($index + 1);
                $item->update(['effective_to' => $next?->effective_from->subDay()]);
            }

            $current = $rates->filter(fn (UnitRentRate $item): bool => $item->effective_from->lte(now()))->last();

            if ($current !== null) {
                $unit->update(['rent_amount' => $current->amount]);
            }

            return $rate;
        });

        $audit->record($request, 'unit.rent_revised', "Scheduled standard rent for {$unit->code} at UGX ".number_format($rate->amount)." from {$effectiveFrom->format('d M Y')}.", $unit, [
            'amount' => $rate->amount,
            'effective_from' => $effectiveFrom->toDateString(),
        ]);

        return to_route('properties.index')->with('success', 'Standard unit rent scheduled. Existing tenant leases and invoices were not changed.');
    }
}
