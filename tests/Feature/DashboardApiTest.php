<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Nozzle;
use App\Models\Purchase;
use App\Models\DailySale;
use App\Models\NozzleReading;
use App\Models\Lubricant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Carbon\Carbon;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed seeders
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

    public function test_dashboard_stats_and_sales(): void
    {
        $admin = $this->getSuperAdmin();
        Sanctum::actingAs($admin);

        // 1. Create Purchase Fuel Inventory (litres)
        // Opening / Purchase records: MS = 10,000L, HSD = 20,000L, XP = 5,000L
        Purchase::create([
            'date' => '2026-05-01',
            'ms' => 10000.00,
            'hsd' => 20000.00,
            'xp' => 5000.00,
            'is_opening' => true,
            'remarks' => 'Opening stocks'
        ]);

        // 2. Fetch Nozzles
        $nozzleMs = Nozzle::where('fuel_type', 'MS')->first();
        $nozzleHsd = Nozzle::where('fuel_type', 'HSD')->first();
        $nozzleXp = Nozzle::where('fuel_type', 'XP')->first();

        // 3. Create a Daily Sale record (date: 2026-05-20)
        // Today is 2026-05-20
        $dailySale = DailySale::create([
            'date' => '2026-05-20',
            'rate_ms' => 100.00,
            'rate_hsd' => 90.00,
            'rate_xp' => 110.00,
            'lubricant_sale' => 500.00,
            'density' => 0.00,
            'credit_payment_received' => 0.00,
            'ac_transfer' => 0.00,
            'online' => 0.00,
            'creditor' => 200.00,
            'expense' => 0.00,
            'staff' => 0.00,
            'owner_1' => 0.00,
            'owner_2' => 0.00,
            'cash_collection' => 1000.00,
            'coin' => 0.00,
            'miss' => 0.00,
            'balance_in_pump' => 15000.00,
        ]);

        // 4. Create Nozzle Readings (sale volumes: MS = 100L, HSD = 200L, XP = 50L)
        // Fuel revenues: MS = 100 * 100 = 10,000 | HSD = 200 * 90 = 18,000 | XP = 50 * 110 = 5,500
        // Total fuel sales amount = 33,500
        // Total sales amount (fuel + lubricant) = 33,500 + 500 = 34,000
        NozzleReading::create([
            'daily_sale_id' => $dailySale->id,
            'nozzle_id' => $nozzleMs->id,
            'closing_reading' => floatval($nozzleMs->opening_reading) + 100.00,
            'sale_qty' => 100.00,
            'amount' => 10000.00
        ]);

        NozzleReading::create([
            'daily_sale_id' => $dailySale->id,
            'nozzle_id' => $nozzleHsd->id,
            'closing_reading' => floatval($nozzleHsd->opening_reading) + 200.00,
            'sale_qty' => 200.00,
            'amount' => 18000.00
        ]);

        NozzleReading::create([
            'daily_sale_id' => $dailySale->id,
            'nozzle_id' => $nozzleXp->id,
            'closing_reading' => floatval($nozzleXp->opening_reading) + 50.00,
            'sale_qty' => 50.00,
            'amount' => 5500.00
        ]);

        // Check stats API
        // For remaining fuel: MS = 10,000 - 100 = 9,900L | HSD = 20,000 - 200 = 19,800L | XP = 5,000 - 50 = 4,950L
        $statsResponse = $this->getJson('/api/dashboard/stats');

        $statsResponse->assertStatus(200)
                      ->assertJsonPath('success', true)
                      ->assertJsonStructure([
                          'success',
                          'stats' => [
                              'fuel_remaining' => ['MS', 'HSD', 'XP', 'total'],
                              'lubricant_stock',
                              'sales_overview' => ['today_sales', 'this_week_sales', 'this_quarter_sales', 'grand_total_sales']
                          ]
                      ]);

        $this->assertEquals(9900.00, floatval($statsResponse->json('stats.fuel_remaining.MS')));
        $this->assertEquals(19800.00, floatval($statsResponse->json('stats.fuel_remaining.HSD')));
        $this->assertEquals(4950.00, floatval($statsResponse->json('stats.fuel_remaining.XP')));

        // Check Sales Aggregation API
        // A. Group by Day (default)
        $salesResponse = $this->getJson('/api/dashboard/sales?group_by=day');
        $salesResponse->assertStatus(200)
                      ->assertJsonPath('success', true)
                      ->assertJsonPath('group_by', 'day')
                      ->assertJsonStructure([
                          'success',
                          'group_by',
                          'sales' => [
                              'current_page',
                              'data' => [
                                  '*' => [
                                      'period',
                                      'raw_date',
                                      'fuel_sales',
                                      'lubricant_sales',
                                      'total_sales',
                                      'credit_sales',
                                      'cash_collected'
                                  ]
                              ]
                          ]
                      ]);

        $salesData = $salesResponse->json('sales.data');
        $this->assertCount(1, $salesData);
        $this->assertEquals('20/05/2026', $salesData[0]['period']);
        $this->assertEquals(33500.00, floatval($salesData[0]['fuel_sales']));
        $this->assertEquals(500.00, floatval($salesData[0]['lubricant_sales']));
        $this->assertEquals(34000.00, floatval($salesData[0]['total_sales']));

        // B. Group by Week
        $weekResponse = $this->getJson('/api/dashboard/sales?group_by=week');
        $weekResponse->assertStatus(200)
                     ->assertJsonPath('success', true)
                     ->assertJsonPath('group_by', 'week');
        
        $weekData = $weekResponse->json('sales.data');
        $this->assertCount(1, $weekData);
        $this->assertStringContainsString('Week of', $weekData[0]['period']);
        $this->assertEquals(34000.00, floatval($weekData[0]['total_sales']));

        // C. Group by Quarter
        $quarterResponse = $this->getJson('/api/dashboard/sales?group_by=quarter');
        $quarterResponse->assertStatus(200)
                        ->assertJsonPath('success', true)
                        ->assertJsonPath('group_by', 'quarter');
        
        $quarterData = $quarterResponse->json('sales.data');
        $this->assertCount(1, $quarterData);
        $this->assertEquals('2026-Q2', $quarterData[0]['period']); // May is Q2
        $this->assertEquals(34000.00, floatval($quarterData[0]['total_sales']));

        // D. Filter by Date Range (no matches expected outside of May 2026)
        $filterResponse = $this->getJson('/api/dashboard/sales?start_date=01/01/2025&end_date=31/12/2025');
        $filterResponse->assertStatus(200);
        $this->assertCount(0, $filterResponse->json('sales.data'));
    }
}
