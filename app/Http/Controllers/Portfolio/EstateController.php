<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\StoreEstateRequest;
use App\Models\Estate;
use App\Models\Section;
use App\Models\SettlementAccount;
use App\Models\Unit;
use App\Models\UnitRentRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EstateController extends Controller
{
    public function index(): Response
    {
        $estates = Estate::query()
            ->with([
                'settlementAccount',
                'sections.units.rentRates.creator',
                'sections.units.activeLease.tenant',
                'sections.units.activeLease.rentRevisions',
            ])
            ->orderBy('id')
            ->get();

        $units = $estates->flatMap(
            fn (Estate $estate) => $estate->sections->flatMap(
                fn (Section $section) => $section->units,
            ),
        );

        return Inertia::render('properties/Index', [
            'summary' => [
                'estate_count' => $estates->count(),
                'section_count' => $estates->sum(fn (Estate $estate) => $estate->sections->count()),
                'unit_count' => $units->count(),
                'monthly_rent' => $units->where('is_active', true)->sum('rent_amount'),
                'setup_required' => $units->where('status', 'setup_required')->count(),
            ],
            'estates' => $estates->map(fn (Estate $estate) => $this->estateData($estate))->values(),
            'settlementAccounts' => SettlementAccount::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(fn (SettlementAccount $account) => [
                    'id' => $account->id,
                    'label' => $account->label,
                    'owner_name' => $account->owner_name,
                    'bank_name' => $account->bank_name,
                    'account_last_four' => $account->account_last_four,
                ]),
        ]);
    }

    public function store(StoreEstateRequest $request): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        Estate::query()->create($data);

        return to_route('properties.index')->with('success', 'Estate created. You can now add flexible sections and units.');
    }

    /** @return array<string, mixed> */
    private function estateData(Estate $estate): array
    {
        $units = $estate->sections->flatMap(fn (Section $section) => $section->units);

        return [
            'id' => $estate->id,
            'name' => $estate->name,
            'code' => $estate->code,
            'location' => $estate->location,
            'address' => $estate->address,
            'color' => $estate->color,
            'is_active' => $estate->is_active,
            'unit_count' => $units->count(),
            'section_count' => $estate->sections->count(),
            'monthly_rent' => $units->where('is_active', true)->sum('rent_amount'),
            'setup_required' => $units->where('status', 'setup_required')->count(),
            'settlement_account' => $estate->settlementAccount === null ? null : [
                'id' => $estate->settlementAccount->id,
                'owner_name' => $estate->settlementAccount->owner_name,
                'bank_name' => $estate->settlementAccount->bank_name,
                'account_last_four' => $estate->settlementAccount->account_last_four,
            ],
            'sections' => $estate->sections->map(fn (Section $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'code' => $section->code,
                'default_rent' => $section->default_rent,
                'unit_count' => $section->units->count(),
                'monthly_rent' => $section->units->where('is_active', true)->sum('rent_amount'),
                'units' => $section->units->map(fn (Unit $unit): array => $this->unitData($unit))->values(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function unitData(Unit $unit): array
    {
        $activeLease = $unit->activeLease;
        $upcomingRate = $unit->rentRates
            ->filter(fn (UnitRentRate $rate): bool => $rate->effective_from->isAfter(now()->startOfMonth()))
            ->sortBy('effective_from')
            ->first();

        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'number' => $unit->number,
            'rent_amount' => $unit->rent_amount,
            'status' => $unit->status,
            'is_active' => $unit->is_active,
            'active_lease' => $activeLease === null ? null : [
                'id' => $activeLease->id,
                'lease_number' => $activeLease->lease_number,
                'tenant_name' => $activeLease->tenant->full_name,
                'rent_amount' => $activeLease->rentForDate(now()->startOfMonth()),
            ],
            'upcoming_rate' => $upcomingRate === null ? null : [
                'amount' => $upcomingRate->amount,
                'effective_from' => $upcomingRate->effective_from->toDateString(),
                'effective_from_label' => $upcomingRate->effective_from->format('d M Y'),
            ],
            'rent_rates' => $unit->rentRates->map(fn (UnitRentRate $rate): array => [
                'id' => $rate->id,
                'amount' => $rate->amount,
                'effective_from' => $rate->effective_from->toDateString(),
                'effective_from_label' => $rate->effective_from->format('d M Y'),
                'effective_to_label' => $rate->effective_to?->format('d M Y'),
                'source' => $rate->source,
                'notes' => $rate->notes,
                'created_by' => $rate->created_by === null ? 'System' : $rate->creator->name,
            ])->values(),
        ];
    }
}
