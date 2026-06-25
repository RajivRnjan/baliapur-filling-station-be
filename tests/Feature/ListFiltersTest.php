<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Nozzle;
use App\Models\Purchase;
use App\Models\DailySale;
use App\Models\Lubricant;
use App\Models\Creditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\LubricantSeeder::class);
        $this->seed(\Database\Seeders\NozzleSeeder::class);
    }

    private function getSuperAdmin()
    {
        return User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);
    }

    public function test_nozzle_filtering(): void
    {
        Sanctum::actingAs($this->getSuperAdmin());

        // HSD nozzles exist from seeder
        $response = $this->getJson('/api/nozzles?fuel_type=HSD');
        $response->assertStatus(200);
        
        $nozzles = $response->json('nozzles.data');
        $this->assertNotEmpty($nozzles);
        foreach ($nozzles as $n) {
            $this->assertEquals('HSD', $n['fuel_type']);
        }

        // Filter for XP which doesn't exist yet (or exists but check count)
        $xpResponse = $this->getJson('/api/nozzles?fuel_type=XP');
        $xpResponse->assertStatus(200);
        $xpNozzles = $xpResponse->json('nozzles.data');
        foreach ($xpNozzles as $n) {
            $this->assertEquals('XP', $n['fuel_type']);
        }
    }

    public function test_purchase_filtering(): void
    {
        Sanctum::actingAs($this->getSuperAdmin());

        // Create opening and regular purchases
        Purchase::create([
            'date' => '2026-05-01',
            'ms' => 1000.00,
            'is_opening' => true,
            'remarks' => 'Opening MS'
        ]);

        Purchase::create([
            'date' => '2026-05-15',
            'ms' => 2000.00,
            'is_opening' => false,
            'remarks' => 'Regular purchase'
        ]);

        // Filter by is_opening = true
        $response = $this->getJson('/api/purchases?is_opening=true');
        $response->assertStatus(200);
        $purchases = $response->json('purchases.data');
        $this->assertCount(1, $purchases);
        $this->assertTrue($purchases[0]['is_opening']);

        // Filter by date range
        $dateResponse = $this->getJson('/api/purchases?start_date=10/05/2026');
        $dateResponse->assertStatus(200);
        $datePurchases = $dateResponse->json('purchases.data');
        $this->assertCount(1, $datePurchases);
        $this->assertEquals('15/05/2026', $datePurchases[0]['date']);
    }

    public function test_daily_sale_filtering(): void
    {
        Sanctum::actingAs($this->getSuperAdmin());

        DailySale::create([
            'date' => '2026-05-10',
            'rate_ms' => 100.00,
            'rate_hsd' => 90.00,
            'rate_xp' => 110.00,
            'lubricant_sale' => 0.00,
            'density' => 0.00,
            'balance_in_pump' => 10000.00,
        ]);

        DailySale::create([
            'date' => '2026-05-20',
            'rate_ms' => 100.00,
            'rate_hsd' => 90.00,
            'rate_xp' => 110.00,
            'lubricant_sale' => 0.00,
            'density' => 0.00,
            'balance_in_pump' => 10000.00,
        ]);

        // Filter by date range
        $response = $this->getJson('/api/daily-sales?start_date=15/05/2026');
        $response->assertStatus(200);
        $sales = $response->json('daily_sales.data');
        $this->assertCount(1, $sales);
        $this->assertEquals('20/05/2026', $sales[0]['date']);
    }

    public function test_lubricant_filtering(): void
    {
        Sanctum::actingAs($this->getSuperAdmin());

        // Verify seeder items
        $response = $this->getJson('/api/lubricants?search=Servo');
        $response->assertStatus(200);
        $lubricants = $response->json('lubricants.data');
        $this->assertNotEmpty($lubricants);
        foreach ($lubricants as $l) {
            $this->assertStringContainsString('Servo', $l['name']);
        }

        // Test stock status filter
        // Servo Futura has 48 stock, seeder low stock items can be tested
        $lowStockResponse = $this->getJson('/api/lubricants?stock_status=low_stock');
        $lowStockResponse->assertStatus(200);
        $lowStockItems = $lowStockResponse->json('lubricants.data');
        foreach ($lowStockItems as $l) {
            $this->assertTrue($l['stock'] <= 5);
        }
    }

    public function test_creditor_filtering(): void
    {
        Sanctum::actingAs($this->getSuperAdmin());

        Creditor::create([
            'name' => 'John Doe',
            'phone' => '+91 99999 88888',
            'email' => 'john@doe.com',
            'opening_balance' => 0.00
        ]);

        Creditor::create([
            'name' => 'Alice Smith',
            'phone' => '+91 77777 66666',
            'email' => 'alice@smith.com',
            'opening_balance' => 0.00
        ]);

        // Search by name
        $response = $this->getJson('/api/creditors?search=John');
        $response->assertStatus(200);
        $creditors = $response->json('creditors.data');
        $this->assertCount(1, $creditors);
        $this->assertEquals('John Doe', $creditors[0]['name']);
    }
}
