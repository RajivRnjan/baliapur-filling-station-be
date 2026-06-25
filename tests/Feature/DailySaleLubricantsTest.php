<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Lubricant;
use App\Models\DailySale;
use App\Models\DailySaleLubricant;
use App\Models\Nozzle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailySaleLubricantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed lubricants and nozzles
        $this->seed(\Database\Seeders\LubricantSeeder::class);
        $this->seed(\Database\Seeders\NozzleSeeder::class);
    }

    private function getSuperAdmin()
    {
        return User::factory()->create([
            'name' => 'Super Admin',
            'email' => env('SUPER_ADMIN_EMAIL', 'msbaliapurfillingstation@gmail.com'),
            'role' => 'SUPER_ADMIN',
        ]);
    }

    /**
     * Test full lubricant sales integration with creation, update, and deletion of a daily sale.
     */
    public function test_daily_sale_lubricants_integration(): void
    {
        $admin = $this->getSuperAdmin();
        Sanctum::actingAs($admin);

        // Fetch two seeded lubricants
        $lubricants = Lubricant::orderBy('name', 'asc')->take(2)->get();
        $lubricant1 = $lubricants[0];
        $lubricant2 = $lubricants[1];

        // Store their original stocks
        $origStock1 = $lubricant1->stock;
        $origStock2 = $lubricant2->stock;

        // Fetch nozzles to construct readings payload
        $nozzles = Nozzle::all();
        $readings = [];
        foreach ($nozzles as $nozzle) {
            $readings[] = [
                'nozzle_id' => $nozzle->id,
                'closing_reading' => floatval($nozzle->opening_reading) + 100.00
            ];
        }

        // 1. Create a Daily Sale with itemized lubricants
        $payload = [
            'date' => '2026-05-20',
            'rate_ms' => 98.17,
            'rate_hsd' => 92.93,
            'rate_xp' => 107.05,
            'density' => 3000.00,
            'credit_payment_received' => 0.00,
            'ac_transfer' => 0.00,
            'online' => 0.00,
            'creditor' => 0.00,
            'expense' => 0.00,
            'staff' => 0.00,
            'owner_1' => 0.00,
            'owner_2' => 0.00,
            'cash_collection' => 0.00,
            'coin' => 0.00,
            'miss' => 0.00,
            'balance_in_pump' => 29120.00,
            'readings' => $readings,
            'lubricants' => [
                [
                    'lubricant_id' => $lubricant1->id,
                    'quantity' => 2,
                    'price' => 350.00
                ],
                [
                    'lubricant_id' => $lubricant2->id,
                    'quantity' => 3,
                    'price' => 150.00
                ]
            ]
        ];

        $response = $this->postJson('/api/daily-sales', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'daily_sale' => [
                         'id',
                         'date',
                         'lubricants',
                         'lubricant_sale'
                     ]
                 ]);

        // Total should be: 2 * 350 + 3 * 150 = 700 + 450 = 1150
        $expectedTotal = 1150.00;
        $this->assertEquals($expectedTotal, floatval($response->json('daily_sale.lubricant_sale')));

        // Verify stock got decremented
        $this->assertEquals($origStock1 - 2, Lubricant::find($lubricant1->id)->stock);
        $this->assertEquals($origStock2 - 3, Lubricant::find($lubricant2->id)->stock);

        // Verify items were written in pivot table
        $dailySaleId = $response->json('daily_sale.id');
        $this->assertDatabaseHas('daily_sale_lubricants', [
            'daily_sale_id' => $dailySaleId,
            'lubricant_id' => $lubricant1->id,
            'quantity' => 2,
            'price' => 350.00,
            'amount' => 700.00
        ]);

        // 2. Update the Daily Sale with modified lubricant sales list
        // Update first lubricant to qty 5, remove second lubricant completely
        $updatePayload = $payload;
        $updatePayload['lubricants'] = [
            [
                'lubricant_id' => $lubricant1->id,
                'quantity' => 5,
                'price' => 360.00
            ]
        ];

        $updateResponse = $this->putJson("/api/daily-sales/{$dailySaleId}", $updatePayload);

        $updateResponse->assertStatus(200)
                       ->assertJsonPath('success', true);

        // New total should be: 5 * 360 = 1800
        $this->assertEquals(1800.00, floatval($updateResponse->json('daily_sale.lubricant_sale')));

        // Verify stock adjustments (First was decreased by 5 from original stock, second restored to original stock)
        $this->assertEquals($origStock1 - 5, Lubricant::find($lubricant1->id)->stock);
        $this->assertEquals($origStock2, Lubricant::find($lubricant2->id)->stock);

        // Verify pivot table got cleaned up and updated
        $this->assertDatabaseHas('daily_sale_lubricants', [
            'daily_sale_id' => $dailySaleId,
            'lubricant_id' => $lubricant1->id,
            'quantity' => 5,
            'price' => 360.00,
            'amount' => 1800.00
        ]);
        $this->assertDatabaseMissing('daily_sale_lubricants', [
            'daily_sale_id' => $dailySaleId,
            'lubricant_id' => $lubricant2->id
        ]);

        // 3. Delete the Daily Sale and verify stock restoration
        $deleteResponse = $this->deleteJson("/api/daily-sales/{$dailySaleId}");
        $deleteResponse->assertStatus(200);

        // Verify stocks are completely restored back to their original levels
        $this->assertEquals($origStock1, Lubricant::find($lubricant1->id)->stock);
        $this->assertEquals($origStock2, Lubricant::find($lubricant2->id)->stock);

        // Verify database is clean
        $this->assertDatabaseMissing('daily_sale_lubricants', [
            'daily_sale_id' => $dailySaleId
        ]);
    }
}
