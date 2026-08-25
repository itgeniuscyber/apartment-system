<?php

namespace Database\Seeders;

use App\Models\Estate;
use App\Models\Section;
use App\Models\SettlementAccount;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    /**
     * Seed the confirmed starting portfolio without overwriting later admin changes.
     */
    public function run(): void
    {
        $samuel = SettlementAccount::query()->firstOrCreate(
            ['label' => 'Samuel portfolio'],
            [
                'owner_name' => 'Samuel Nyika',
                'bank_name' => 'Centenary Bank',
                'account_last_four' => '3432',
                'currency' => 'UGX',
            ],
        );

        $racheal = SettlementAccount::query()->firstOrCreate(
            ['label' => 'Racheal portfolio'],
            [
                'owner_name' => 'Racheal Kayombya',
                'bank_name' => 'Centenary Bank',
                'account_last_four' => '5278',
                'currency' => 'UGX',
            ],
        );

        $this->seedEstate(
            name: 'Kisaasi',
            code: 'KIS',
            color: '#f26745',
            settlementAccount: $samuel,
            sections: [
                [
                    'name' => 'Upper',
                    'code' => 'UP',
                    'rents' => [...array_fill(0, 10, 700000), ...array_fill(0, 4, 400000)],
                ],
                [
                    'name' => 'Lower',
                    'code' => 'LOW',
                    'rents' => [
                        ...array_fill(0, 10, 700000),
                        ...array_fill(0, 6, 800000),
                        ...array_fill(0, 3, 550000),
                    ],
                ],
            ],
        );

        $this->seedEstate(
            name: 'Gayaza',
            code: 'GAY',
            color: '#171715',
            settlementAccount: $racheal,
            sections: [
                ['name' => 'Lower', 'code' => 'LOW', 'rents' => array_fill(0, 8, 500000)],
                ['name' => 'Middle', 'code' => 'MID', 'rents' => array_fill(0, 8, 500000)],
                ['name' => 'Upper', 'code' => 'UP', 'rents' => array_fill(0, 8, 500000)],
            ],
        );

        $this->seedEstate(
            name: 'Kitebi',
            code: 'KIT',
            color: '#e1a43b',
            settlementAccount: $racheal,
            sections: [
                ['name' => 'Main', 'code' => 'MAIN', 'rents' => array_fill(0, 5, 500000)],
            ],
        );

        $this->seedEstate(
            name: 'Lubowa',
            code: 'LUB',
            color: '#1f9d74',
            settlementAccount: $samuel,
            sections: [
                ['name' => 'Main', 'code' => 'MAIN', 'rents' => array_fill(0, 4, 100000)],
            ],
        );
    }

    /**
     * @param  list<array{name: string, code: string, rents: list<int>}>  $sections
     */
    private function seedEstate(
        string $name,
        string $code,
        string $color,
        SettlementAccount $settlementAccount,
        array $sections,
    ): void {
        $estate = Estate::query()->firstOrCreate(
            ['code' => $code],
            [
                'settlement_account_id' => $settlementAccount->id,
                'name' => $name,
                'location' => $name,
                'color' => $color,
            ],
        );

        foreach ($sections as $sectionIndex => $sectionData) {
            $section = Section::query()->firstOrCreate(
                [
                    'estate_id' => $estate->id,
                    'code' => $sectionData['code'],
                ],
                [
                    'name' => $sectionData['name'],
                    'sort_order' => $sectionIndex + 1,
                    'default_rent' => $sectionData['rents'][0],
                ],
            );

            foreach ($sectionData['rents'] as $index => $rentAmount) {
                $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $unitCode = "{$code}-{$sectionData['code']}-{$number}";

                $unit = Unit::query()->firstOrCreate(
                    ['code' => $unitCode],
                    [
                        'section_id' => $section->id,
                        'number' => $number,
                        'rent_amount' => $rentAmount,
                        'status' => 'setup_required',
                    ],
                );

                $unit->rentRates()->firstOrCreate(
                    ['effective_from' => '2026-08-01'],
                    [
                        'amount' => $rentAmount,
                        'source' => 'initial_setup',
                        'notes' => $name === 'Gayaza'
                            ? 'Provisional starting rate; confirm and adjust in administration.'
                            : 'Starting rate supplied during portfolio setup.',
                    ],
                );
            }
        }
    }
}
