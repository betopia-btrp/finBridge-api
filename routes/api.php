

<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\LoanProductController;
use App\Http\Controllers\Api\MfiController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {



    // public endpoints
    Route::post('/auth/register/mfi', [AuthController::class, 'registerMfi']);
    Route::post('/auth/register/entrepreneur', [AuthController::class, 'registerEntrepreneur']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/mfis', [MfiController::class, 'index']);

    Route::get('/loan-products', [LoanProductController::class, 'index']);

    // subscription
    Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscription-plans/{id}', [SubscriptionController::class, 'showPlan']);

    // ✅ SSL PAYMENT CALLBACKS (PUBLIC — NO AUTH)
    Route::post('/payment/success', [SubscriptionController::class, 'paymentSuccess']);
    Route::post('/payment/fail', [SubscriptionController::class, 'paymentFail']);
    Route::post('/payment/cancel', [SubscriptionController::class, 'paymentCancel']);





    Route::middleware('auth:sanctum')->post('/auth/logout', [AuthController::class, 'logout']);

    // 🔒 PROTECTED
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'User data',
                'data' => $request->user()
            ]);
        });
    });

    // 🔒 MFI ONLY

    // 🔓 allow payment even if expired
    Route::middleware(['auth:sanctum', 'role:mfi_admin'])->group(function () {
        Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::get('/mfi/subscription', [SubscriptionController::class, 'current']);
        Route::get('/mfi/payments', [SubscriptionController::class, 'paymentHistory']);
        Route::get('/mfi/invoice/{transactionId}', [SubscriptionController::class, 'invoice']);
    });

    Route::middleware(['auth:sanctum', 'role:mfi_admin', 'check.subscription'])->group(function () {

        Route::get('/mfi/dashboard', function () {
            return response()->json([
                'success' => true,
                'message' => 'MFI Dashboard',
                'data' => null
            ]);
        });

        Route::post('/mfi/loan-products', [LoanProductController::class, 'store']);
        Route::get('/mfi/loan-products', [LoanProductController::class, 'myProducts']);
        Route::put('/mfi/loan-products/{id}', [LoanProductController::class, 'update']);

        // FIX : TODO
        Route::delete('/mfi/loan-products/{id}', [LoanProductController::class, 'delete']);



        Route::get('/mfi/applications', [LoanApplicationController::class, 'mfiApplications']);
        Route::get('/mfi/applications/{id}', [LoanApplicationController::class, 'show']);
        Route::post('/mfi/applications/{id}/approve', [LoanApplicationController::class, 'approve']);
        Route::post('/mfi/applications/{id}/reject', [LoanApplicationController::class, 'reject']);
    });

    // 🔒 ENTREPRENEUR ONLY
    Route::middleware(['auth:sanctum', 'role:entrepreneur'])->group(function () {

        Route::get('/entrepreneur/dashboard', function () {
            return response()->json([
                'success' => true,
                'message' => 'Entrepreneur Dashboard',
                'data' => null
            ]);
        });

        Route::post('/loan/apply', [LoanApplicationController::class, 'apply']);
        Route::get('/entrepreneur/applications', [LoanApplicationController::class, 'myApplications']);
    });

    // 🔒 platfrom admin ONLY
    Route::middleware(['auth:sanctum', 'role:platform_admin'])->group(function () {

        Route::post('/admin/subscription-plans', [SubscriptionController::class, 'storePlan']);
        Route::put('/admin/subscription-plans/{id}', [SubscriptionController::class, 'updatePlan']);
        Route::delete('/admin/subscription-plans/{id}', [SubscriptionController::class, 'deletePlan']);

        // 🔥 NEW
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/admin/mfis', [MfiController::class, 'adminList']);

        Route::get('/admin/reports/revenue', [AdminController::class, 'revenueReport']);

        Route::get('/admin/payments', [SubscriptionController::class, 'adminPayments']);

        Route::get('/admin/applications', [LoanApplicationController::class, 'adminAll']);

        Route::patch('/admin/subscriptions/{id}/force', [SubscriptionController::class, 'forceActivate']);
    });
});
