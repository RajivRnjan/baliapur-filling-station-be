<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Lubricant;
use App\Models\Creditor;
use App\Models\CreditorTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LubricantsAndCreditorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders on the isolated test database
        $this->seed(\Database\Seeders\LubricantSeeder::class);
        $this->seed(\Database\Seeders\CreditorSeeder::class);
    }

    private function getSuperAdmin()
    {
        return User::factory()->create([
            'name' => 'Super Admin',
            'email' => env('SUPER_ADMIN_EMAIL', 'msbaliapurfillingstation@gmail.com'),
            'role' => 'SUPER_ADMIN',
        ]);
    }

    private function getStandardAdmin()
    {
        return User::factory()->create([
            'name' => 'Standard Admin',
            'email' => 'admin@petrolpump.com',
            'role' => 'ADMIN',
        ]);
    }

    /**
     * Test fetching lubricants lists and verifying seeded data.
     */
    public function test_lubricants_list_and_management(): void
    {
        $user = $this->getSuperAdmin();
        Sanctum::actingAs($user);

        // 1. Get Lubricants
        $response = $this->getJson('/api/lubricants');
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'lubricants' => [
                         'current_page',
                         'data' => [
                             '*' => ['id', 'name', 'qty_name', 'price', 'stock', 'created_at', 'updated_at']
                         ]
                     ]
                 ]);

        // Ensure at least the seeded lubricants exist
        $lubricants = $response->json('lubricants.data');
        $this->assertCount(5, $lubricants);

        // Find Super Servo 20W40 MG (1 lt)
        $superServo = collect($lubricants)->firstWhere('qty_name', '1 lt');
        $this->assertNotNull($superServo);
        $this->assertEquals('Super Servo 20W40 MG', $superServo['name']);
        $this->assertEquals(350.00, floatval($superServo['price']));

        // 2. Create a new Lubricant
        $newLubricantData = [
            'name' => 'Test Lubricant Product',
            'qty_name' => '250ml',
            'price' => 120.00,
            'stock' => 15
        ];
        $storeResponse = $this->postJson('/api/lubricants', $newLubricantData);
        $storeResponse->assertStatus(201)
                     ->assertJson([
                         'success' => true,
                         'message' => 'Lubricant created successfully'
                     ]);
        $newLubricantId = $storeResponse->json('lubricant.id');

        // 3. Update the Lubricant
        $updateResponse = $this->putJson("/api/lubricants/{$newLubricantId}", [
            'name' => 'Updated Test Lubricant Product',
            'qty_name' => '250ml',
            'price' => 140.00,
            'stock' => 20
        ]);
        $updateResponse->assertStatus(200)
                       ->assertJsonPath('lubricant.price', '140.00');

        // 4. Update fails for standard Admin
        Sanctum::actingAs($this->getStandardAdmin());
        $unauthorizedUpdateResponse = $this->putJson("/api/lubricants/{$newLubricantId}", [
            'name' => 'Unauthorized Product Update',
            'qty_name' => '250ml',
            'price' => 999.00,
            'stock' => 20
        ]);
        $unauthorizedUpdateResponse->assertStatus(403);

        // 5. Delete works for Super Admin
        Sanctum::actingAs($user);
        $deleteResponse = $this->deleteJson("/api/lubricants/{$newLubricantId}");
        $deleteResponse->assertStatus(200);
    }

    /**
     * Test fetching creditors list and ledger calculations.
     */
    public function test_creditors_ledger_and_calculations(): void
    {
        $user = $this->getSuperAdmin();
        Sanctum::actingAs($user);

        // 1. Get Creditors
        $response = $this->getJson('/api/creditors');
        $response->assertStatus(200);
        
        $creditors = $response->json('creditors.data');
        $this->assertCount(4, $creditors);

        // Verify Jivanta Group was seeded
        $jivanta = collect($creditors)->firstWhere('name', 'Jivanta Group');
        $this->assertNotNull($jivanta);
        $this->assertEquals(250000.00, floatval($jivanta['opening_balance']));

        // 2. Fetch Jivanta Group Ledger and check exact screenshot matches!
        $ledgerResponse = $this->getJson("/api/creditors/{$jivanta['id']}/ledger");
        $ledgerResponse->assertStatus(200)
                       ->assertJsonStructure([
                           'success',
                           'creditor' => ['id', 'name', 'opening_balance', 'current_balance'],
                           'ledger' => [
                               '*' => ['id', 'date', 'credit_amount', 'payment_amount', 'balance', 'remark']
                           ]
                       ]);

        $ledger = $ledgerResponse->json('ledger');
        
        // Let's assert that the running balances match the Excel sheet screenshot exactly!
        // Row 0: Opening balance: 250,000
        $this->assertEquals('Opening Balance', $ledger[0]['date']);
        $this->assertEquals(250000.00, floatval($ledger[0]['balance']));
        
        // Row 1 (2026-05-01): Credit 20,000 | Running Balance: 270,000
        $this->assertEquals('01/05/2026', $ledger[1]['date']);
        $this->assertEquals(20000.00, floatval($ledger[1]['credit_amount']));
        $this->assertEquals(0.00, floatval($ledger[1]['payment_amount']));
        $this->assertEquals(270000.00, floatval($ledger[1]['balance']));

        // Row 2 (2026-05-02): Credit 60,000 | Payment 300,000 | Running Balance: 30,000
        $this->assertEquals('02/05/2026', $ledger[2]['date']);
        $this->assertEquals(60000.00, floatval($ledger[2]['credit_amount']));
        $this->assertEquals(300000.00, floatval($ledger[2]['payment_amount']));
        $this->assertEquals(30000.00, floatval($ledger[2]['balance']));

        // Row 3 (2026-05-03): Credit 300,000 | Running Balance: 330,000
        $this->assertEquals('03/05/2026', $ledger[3]['date']);
        $this->assertEquals(300000.00, floatval($ledger[3]['credit_amount']));
        $this->assertEquals(0.00, floatval($ledger[3]['payment_amount']));
        $this->assertEquals(330000.00, floatval($ledger[3]['balance']));

        // Row 4 (2026-05-04): Credit 45,000 | Running Balance: 375,000
        $this->assertEquals('04/05/2026', $ledger[4]['date']);
        $this->assertEquals(45000.00, floatval($ledger[4]['credit_amount']));
        $this->assertEquals(0.00, floatval($ledger[4]['payment_amount']));
        $this->assertEquals(375000.00, floatval($ledger[4]['balance']));

        // Check if dynamic current balance matches final running balance (375,000)
        $this->assertEquals(375000.00, floatval($ledgerResponse->json('creditor.current_balance')));

        // 3. Post a new transaction and verify recalculation
        // Row 5 (2026-05-05): Credit 25,000 | Payment 100,000 | Running Balance should be: 375,000 + 25,000 - 100,000 = 300,000
        $txnResponse = $this->postJson("/api/creditors/{$jivanta['id']}/transactions", [
            'date' => '2026-05-05',
            'credit_amount' => 25000.00,
            'payment_amount' => 100000.00,
            'remark' => 'Test dynamic recalculation entry'
        ]);
        $txnResponse->assertStatus(201);
        $this->assertEquals(300000.00, floatval($txnResponse->json('current_balance')));

        // Fetch ledger again to verify the running balance on the new row
        $newLedgerResponse = $this->getJson("/api/creditors/{$jivanta['id']}/ledger");
        $newLedger = $newLedgerResponse->json('ledger');
        $this->assertEquals(300000.00, floatval($newLedger[5]['balance']));

        // Clean up our test transaction so we leave database in seeded state
        $newTxnId = $txnResponse->json('transaction.id');
        $deleteTxnResponse = $this->deleteJson("/api/creditors/transactions/{$newTxnId}");
        $deleteTxnResponse->assertStatus(200);

        // Verify balance restored back to 375,000
        $this->assertEquals(375000.00, floatval($deleteTxnResponse->json('current_balance')));
    }
}
