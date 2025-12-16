<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create the test user
        $user = User::where('email', 'test@example.com')->first();

        if (!$user) {
            $this->command->warn('Test user not found. Please run UserSeeder first.');
            return;
        }

        // Seed assets for the test user
        $assets = [
            [
                'symbol' => 'BTC',
                'amount' => 0.5,
                'locked_amount' => 0.1,
            ],
            [
                'symbol' => 'ETH',
                'amount' => 2.5,
                'locked_amount' => 0.5,
            ],
            [
                'symbol' => 'USDT',
                'amount' => 5000.0,
                'locked_amount' => 1000.0,
            ],
            [
                'symbol' => 'BNB',
                'amount' => 10.0,
                'locked_amount' => 2.0,
            ],
        ];

        foreach ($assets as $assetData) {
            Asset::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'symbol' => $assetData['symbol'],
                ],
                [
                    'amount' => $assetData['amount'],
                    'locked_amount' => $assetData['locked_amount'],
                ]
            );
        }

        $this->command->info('Assets seeded successfully for test user.');
    }
}
