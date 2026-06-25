<?php

namespace Database\Seeders;

use App\Models\Creditor;
use App\Models\CreditorTransaction;
use Illuminate\Database\Seeder;

class CreditorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Jivanta Group
        $jivanta = Creditor::create([
            'name' => 'Jivanta Group',
            'opening_balance' => 250000.00,
            'phone' => '+91 98765 43210',
            'email' => 'contact@jivanta.com',
        ]);

        // Seed the exact transactions from the Excel screenshot
        $transactions = [
            [
                'date' => '2026-05-01',
                'credit_amount' => 20000.00,
                'payment_amount' => 0.00,
                'remark' => 'Regular credit fuel purchase',
            ],
            [
                'date' => '2026-05-02',
                'credit_amount' => 60000.00,
                'payment_amount' => 300000.00,
                'remark' => 'Clearing balance bank transfer payment',
            ],
            [
                'date' => '2026-05-03',
                'credit_amount' => 300000.00,
                'payment_amount' => 0.00,
                'remark' => 'Bulk diesel delivery',
            ],
            [
                'date' => '2026-05-04',
                'credit_amount' => 45000.00,
                'payment_amount' => 0.00,
                'remark' => 'Regular credit purchase',
            ],
        ];

        foreach ($transactions as $txn) {
            $jivanta->transactions()->create($txn);
        }

        // 2. Seed other creditors with 0.00 opening balance
        $otherCreditors = [
            [
                'name' => 'Agarwal and company',
                'opening_balance' => 0.00,
                'phone' => '+91 99887 76655',
                'email' => 'agarwal@company.com',
            ],
            [
                'name' => 'Mahesh Kumar Yadav',
                'opening_balance' => 0.00,
                'phone' => '+91 91234 56789',
                'email' => 'maheshyadav@gmail.com',
            ],
            [
                'name' => 'Anil kumar mahto',
                'opening_balance' => 0.00,
                'phone' => '+91 95432 10987',
                'email' => 'anilmahto@gmail.com',
            ],
        ];

        foreach ($otherCreditors as $creditor) {
            Creditor::create($creditor);
        }
    }
}
