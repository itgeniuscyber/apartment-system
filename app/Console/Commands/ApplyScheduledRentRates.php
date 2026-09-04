<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\UnitRentRate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ApplyScheduledRentRates extends Command
{
    /** @var string */
    protected $signature = 'rent:apply-scheduled-rates {--date= : Effective date to apply in YYYY-MM-DD format}';

    /** @var string */
    protected $description = 'Apply effective standard unit and tenant lease rent rates to their current cached amounts';

    public function handle(): int
    {
        $date = $this->option('date') === null
            ? now()->startOfDay()
            : CarbonImmutable::parse((string) $this->option('date'))->startOfDay();
        $updatedUnits = 0;
        $updatedLeases = 0;

        Unit::query()->where('is_active', true)->chunkById(200, function ($units) use ($date, &$updatedUnits): void {
            foreach ($units as $unit) {
                $rate = UnitRentRate::query()
                    ->where('unit_id', $unit->id)
                    ->whereDate('effective_from', '<=', $date)
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id')
                    ->first();

                if ($rate !== null && $unit->rent_amount !== $rate->amount) {
                    $unit->update(['rent_amount' => $rate->amount]);
                    $updatedUnits++;
                }
            }
        });

        Lease::query()
            ->with('rentRevisions')
            ->whereIn('status', ['draft', 'active'])
            ->chunkById(200, function ($leases) use ($date, &$updatedLeases): void {
                foreach ($leases as $lease) {
                    $rentAmount = $lease->rentForDate($date);

                    if ($lease->rent_amount !== $rentAmount) {
                        $lease->update(['rent_amount' => $rentAmount]);
                        $updatedLeases++;
                    }
                }
            });

        $this->info("Applied {$updatedUnits} standard unit rates and {$updatedLeases} tenant lease rates.");

        return self::SUCCESS;
    }
}
