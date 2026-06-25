<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NozzleController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\DailySaleController;
use App\Http\Controllers\Api\LubricantController;
use App\Http\Controllers\Api\CreditorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/forgot-password/send-otp', [\App\Http\Controllers\Api\PasswordResetController::class, 'sendOtp']);
Route::post('/forgot-password/verify-otp', [\App\Http\Controllers\Api\PasswordResetController::class, 'verifyOtp']);
Route::post('/forgot-password/reset', [\App\Http\Controllers\Api\PasswordResetController::class, 'resetPassword']);

// Public Direct Migration Webhook (runs php artisan migrate --force via URL hit)
Route::get('/deploy/migrate', function (Request $request) {

    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        return response()->json([
            'success' => true,
            'message' => 'Migrations executed successfully.',
            'output' => $output
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Migration failed: ' . $e->getMessage()
        ], 500);
    }
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Admin management (Super Admin only)
    Route::apiResource('admins', \App\Http\Controllers\Api\AdminController::class)->except(['show']);

    // Nozzle routes
    Route::apiResource('nozzles', NozzleController::class);

    // Purchase routes
    Route::get('purchases/export', [PurchaseController::class, 'export']);
    Route::get('purchases/export/pdf', [PurchaseController::class, 'exportPdf']);
    Route::apiResource('purchases', PurchaseController::class);

    // Daily Sales routes
    Route::get('daily-sales/cash-balance', [DailySaleController::class, 'getCashBalance']);
    Route::get('daily-sales/export', [DailySaleController::class, 'export']);
    Route::get('daily-sales/export/pdf', [DailySaleController::class, 'exportPdf']);
    Route::get('daily-sales/previous-readings', [DailySaleController::class, 'getPreviousReadings']);
    Route::apiResource('daily-sales', DailySaleController::class);
    Route::get('pump-expenses/names', [DailySaleController::class, 'getPumpExpenseNames']);
    Route::get('pump-expenses/export', [DailySaleController::class, 'exportPumpExpenses']);
    Route::get('pump-expenses/export/pdf', [DailySaleController::class, 'exportPumpExpensesPdf']);
    Route::get('pump-expenses', [DailySaleController::class, 'getPumpExpenses']);
    Route::get('owner-payouts/export', [DailySaleController::class, 'exportOwnerPayouts']);
    Route::get('owner-payouts/export/pdf', [DailySaleController::class, 'exportOwnerPayoutsPdf']);
    Route::get('owner-payouts', [DailySaleController::class, 'getOwnerPayouts']);

    // Lubricant routes
    Route::post('lubricants/{id}/add-stock', [LubricantController::class, 'addStock']);
    Route::get('lubricants-sales-history', [LubricantController::class, 'salesHistory']);
    Route::apiResource('lubricants', LubricantController::class);

    // Creditor routes
    Route::apiResource('creditors', CreditorController::class);
    Route::get('creditors/{id}/ledger', [CreditorController::class, 'getLedger']);
    Route::get('creditors/{id}/ledger/export', [CreditorController::class, 'exportLedger']);
    Route::get('creditors/{id}/ledger/export/pdf', [CreditorController::class, 'exportLedgerPdf']);
    Route::post('creditors/{id}/transactions', [CreditorController::class, 'storeTransaction']);
    Route::put('creditors/transactions/{transaction_id}', [CreditorController::class, 'updateTransaction']);
    Route::delete('creditors/transactions/{transaction_id}', [CreditorController::class, 'destroyTransaction']);

    // Dashboard routes
    Route::get('dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'getStats']);
    Route::get('dashboard/sales', [\App\Http\Controllers\Api\DashboardController::class, 'getSales']);
    
    // Staff Salaries route
    Route::get('staff-salaries/export', [\App\Http\Controllers\Api\StaffSalaryController::class, 'export']);
    Route::get('staff-salaries/export/pdf', [\App\Http\Controllers\Api\StaffSalaryController::class, 'exportPdf']);
    Route::get('staff-salaries', [\App\Http\Controllers\Api\StaffSalaryController::class, 'getStaffSalaries']);
    // Cashbook (Receipts & Payments) routes
    Route::get('cashbooks/export', [\App\Http\Controllers\Api\CashbookController::class, 'export']);
    Route::get('cashbooks/export/pdf', [\App\Http\Controllers\Api\CashbookController::class, 'exportPdf']);
    Route::apiResource('cashbooks', \App\Http\Controllers\Api\CashbookController::class);
});
