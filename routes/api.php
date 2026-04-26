

<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LoanApplicationController;
use App\Http\Controllers\Api\LoanProductController;
use App\Http\Controllers\Api\MfiController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/test-mail', function () {
        \Illuminate\Support\Facades\Mail::raw('Test email from FinBridge', function ($message) {
            $message->to('test@example.com')
                ->subject('Test Mail');
        });

        return 'Mail sent';
    });

    // public endpoints
    Route::post('/auth/register/mfi', [AuthController::class, 'registerMfi']);
    Route::post('/auth/register/entrepreneur', [AuthController::class, 'registerEntrepreneur']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/mfis', [MfiController::class, 'index']);

    Route::get('/loan-products', [LoanProductController::class, 'index']);

    // subscription
    Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscription-plans/{id}', [SubscriptionController::class, 'showPlan']);





    Route::post('/payment/confirm', [SubscriptionController::class, 'confirmPayment']);

    // // SSL CALLBACKS (NO AUTH, PUBLIC)
    // Route::post('/payment/success', [SubscriptionController::class, 'paymentSuccess']);
    // Route::post('/payment/fail', [SubscriptionController::class, 'paymentFail']);
    // Route::post('/payment/cancel', [SubscriptionController::class, 'paymentCancel']);;


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
    Route::middleware(['auth:sanctum', 'role:mfi_admin'])->group(function () {

        Route::get('/mfi/dashboard', function () {
            return response()->json([
                'success' => true,
                'message' => 'MFI Dashboard',
                'data' => null
            ]);
        });

        Route::post('/mfi/loan-products', [LoanProductController::class, 'store']);
        Route::get('/mfi/applications', [LoanApplicationController::class, 'mfiApplications']);
        Route::get('/mfi/applications/{id}', [LoanApplicationController::class, 'show']);
        Route::post('/mfi/applications/{id}/approve', [LoanApplicationController::class, 'approve']);
        Route::post('/mfi/applications/{id}/reject', [LoanApplicationController::class, 'reject']);
        Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade']);

        Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe']);
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
    });

    // 🔒 platfrom admin ONLY
    Route::middleware(['auth:sanctum', 'role:platform_admin'])->group(function () {
        Route::post('/admin/subscription-plans', [SubscriptionController::class, 'storePlan']);
        Route::put('/admin/subscription-plans/{id}', [SubscriptionController::class, 'updatePlan']);
        Route::delete('/admin/subscription-plans/{id}', [SubscriptionController::class, 'deletePlan']);
    });
});
