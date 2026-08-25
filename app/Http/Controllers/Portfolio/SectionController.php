<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\StoreSectionRequest;
use App\Models\Estate;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    public function store(StoreSectionRequest $request): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $estate = Estate::query()->findOrFail((int) $data['estate_id']);
        $unitCount = (int) $data['unit_count'];
        $startNumber = (int) $data['start_number'];
        $rentAmount = (int) $data['default_rent'];
        $prefix = (string) ($data['unit_prefix'] ?? "{$estate->code}-{$data['code']}");
        $prefix = rtrim($prefix, '-').'-';
        $numberWidth = max(2, strlen((string) ($startNumber + $unitCount - 1)));

        $units = collect(range($startNumber, $startNumber + $unitCount - 1))
            ->map(fn (int $number) => [
                'number' => str_pad((string) $number, $numberWidth, '0', STR_PAD_LEFT),
                'code' => $prefix.str_pad((string) $number, $numberWidth, '0', STR_PAD_LEFT),
            ]);

        if (Unit::query()->whereIn('code', $units->pluck('code'))->exists()) {
            throw ValidationException::withMessages([
                'unit_prefix' => 'That unit prefix and number range overlaps existing unit codes.',
            ]);
        }

        DB::transaction(function () use ($data, $estate, $rentAmount, $units, $request): void {
            $section = $estate->sections()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'default_rent' => $rentAmount,
                'sort_order' => ((int) Section::query()->where('estate_id', $estate->id)->max('sort_order')) + 1,
            ]);

            foreach ($units as $unitData) {
                $unit = $section->units()->create([
                    ...$unitData,
                    'rent_amount' => $rentAmount,
                    'status' => 'setup_required',
                ]);

                $unit->rentRates()->create([
                    'amount' => $rentAmount,
                    'effective_from' => now()->startOfMonth()->toDateString(),
                    'source' => 'admin',
                    'notes' => 'Initial rate when the section was created.',
                    'created_by' => $request->user()?->id,
                ]);
            }
        });

        return to_route('properties.index')->with('success', "{$data['name']} section created with {$unitCount} units.");
    }
}
