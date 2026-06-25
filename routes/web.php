<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migration', function() {
    return redirect('/run-migrations');
});

Route::get('/run-migrations', function () {
    try {
        // Run database migrations safely
        Artisan::call('migrate', ['--force' => true]);
        $migrationOutput = Artisan::output();

        // Seed the Super Admin account
        Artisan::call('db:seed', ['--class' => 'AdminSeeder', '--force' => true]);
        $seederOutput = Artisan::output();

        // Clear Caches to ensure the new .env and routes take effect
        Artisan::call('optimize:clear');
        $cacheOutput = Artisan::output();

        return "<h3>✅ Success! Database Migrated Successfully!</h3>
                <p><b>Migrations Output:</b></p><pre>{$migrationOutput}</pre>
                <p><b>Seeder Output:</b></p><pre>{$seederOutput}</pre>
                <p><b>Cache Output:</b></p><pre>{$cacheOutput}</pre>
                <p style='color: red'><b>SECURITY WARNING:</b> Please remove this route from routes/web.php after deployment!</p>";
    } catch (\Exception $e) {
        return "<h3>❌ Error:</h3><pre>" . $e->getMessage() . "</pre>";
    }
});

Route::get('/cleanup-duplicates', function () {
    try {
        \Illuminate\Support\Facades\DB::beginTransaction();
        
        // 1. Delete all Daily Sale generated transactions
        \App\Models\CreditorTransaction::where(function ($query) {
            $query->where('remark', 'like', 'Daily Sale Credit Given%')
                  ->orWhere('remark', 'like', 'Daily Sale Credit Received%');
        })->delete();

        // 2. Loop through all DailySales and recreate them exactly once
        $sales = \App\Models\DailySale::with(['creditPayments', 'creditGivens'])->get();
        
        foreach ($sales as $sale) {
            foreach ($sale->creditPayments as $payment) {
                if ($payment->amount > 0) {
                    \App\Models\CreditorTransaction::create([
                        'creditor_id' => $payment->creditor_id,
                        'date' => $sale->date,
                        'credit_amount' => 0.00,
                        'payment_amount' => $payment->amount,
                        'remark' => 'Daily Sale Credit Received',
                    ]);
                }
            }

            foreach ($sale->creditGivens as $given) {
                if ($given->amount > 0) {
                    \App\Models\CreditorTransaction::create([
                        'creditor_id' => $given->creditor_id,
                        'date' => $sale->date,
                        'credit_amount' => $given->amount,
                        'payment_amount' => 0.00,
                        'remark' => $given->remark ? 'Daily Sale Credit Given - ' . $given->remark : 'Daily Sale Credit Given',
                    ]);
                }
            }
        }
        
        \Illuminate\Support\Facades\DB::commit();

        return "<h3>✅ Success! All duplicated creditor transactions have been cleaned up and perfectly synced!</h3>";
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        return "<h3>❌ Error:</h3><pre>" . $e->getMessage() . "</pre>";
    }
});
