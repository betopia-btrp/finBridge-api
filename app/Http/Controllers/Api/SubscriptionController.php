<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SubscriptionController extends Controller
{

    public function plans()
    {
        $plans = DB::table('subscription_plans')
            ->where('status', 'active')
            ->select('id', 'name', 'price_bdt')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    public function showPlan($id)
    {
        $plan = DB::table('subscription_plans')
            ->where('id', $id)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }

    public function storePlan(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:subscription_plans,name',
            'price_bdt' => 'required|numeric|min:0',
        ]);

        $id = Str::uuid();

        DB::table('subscription_plans')->insert([
            'id' => $id,
            'name' => $request->name,
            'price_bdt' => $request->price_bdt,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan created',
            'data' => ['id' => $id]
        ]);
    }

    public function updatePlan(Request $request, $id)
    {
        $plan = DB::table('subscription_plans')->where('id', $id)->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        DB::table('subscription_plans')
            ->where('id', $id)
            ->update([
                'name' => $request->name ?? $plan->name,
                'price_bdt' => $request->price_bdt ?? $plan->price_bdt,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan updated'
        ]);
    }

    public function deletePlan($id)
    {
        $plan = DB::table('subscription_plans')->where('id', $id)->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        DB::table('subscription_plans')
            ->where('id', $id)
            ->update([
                'status' => 'inactive',
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted'
        ]);
    }

    public function subscribe(Request $request)
    {
        // validation
        $request->validate([
            'plan_id' => 'required|uuid',
        ]);

        $user = $request->user();

        // safety check
        if (!$user || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not linked to MFI'
            ], 400);
        }

        // prevent duplicate subscription

        $existingActive = DB::table('subscriptions')
            ->where('mfi_id', $user->mfi_id)
            ->where('status', 'active')
            ->first();

        if ($existingActive) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active paid plan'
            ], 400);
        }


        // get plan
        $plan = DB::table('subscription_plans')
            ->where('id', $request->plan_id)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid plan'
            ], 400);
        }

        DB::beginTransaction();

        try {

            // 1. create subscription
            $subscriptionId = (string) Str::uuid();

            // DB::table('subscriptions')
            //     ->where('mfi_id', $user->mfi_id)
            //     ->where('status', 'trial')
            //     ->update([
            //         'status' => 'expired',
            //         'updated_at' => now(),
            //     ]);

            DB::table('subscriptions')->insert([
                'id' => $subscriptionId,
                'mfi_id' => $user->mfi_id,
                'plan_id' => $plan->id,
                'start_date' => now(),
                'status' => 'pending_payment',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. create transaction
            $transactionId = (string) Str::uuid();

            DB::table('transactions')->insert([
                'id' => $transactionId,
                'mfi_id' => $user->mfi_id,
                'subscription_id' => $subscriptionId,
                'amount' => $plan->price_bdt,
                'status' => 'pending',
                'payment_gateway' => 'sslcommerz',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $backend = config('app.url');

            // 3. call SSL
            $response = Http::asForm()->post(
                'https://sandbox.sslcommerz.com/gwprocess/v4/api.php',
                [
                    'store_id' => env('SSL_STORE_ID'),
                    'store_passwd' => env('SSL_STORE_PASSWORD'),
                    'total_amount' => $plan->price_bdt,
                    'currency' => 'BDT',
                    'tran_id' => $transactionId,

                    'success_url' => $backend . '/api/v1/payment/success',
                    'fail_url'    => $backend . '/api/v1/payment/fail',
                    'cancel_url'  => $backend . '/api/v1/payment/cancel',

                    // 'success_url' => 'http://127.0.0.1:8000/api/v1/payment/success',
                    // 'fail_url' => 'http://127.0.0.1:8000/api/v1/payment/fail',
                    // 'cancel_url' => 'http://127.0.0.1:8000/api/v1/payment/cancel',



                    'cus_name' => $user->name,
                    'cus_email' => $user->email,
                    'cus_phone' => $user->phone ?? '01700000000',

                    'product_name' => 'Subscription Plan',
                    'product_category' => 'Subscription',
                    'product_profile' => 'general',
                ]
            );

            $responseData = $response->json();

            // DEBUG (optional)
            // dd($responseData);

            if (!isset($responseData['GatewayPageURL'])) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'SSL init failed',
                    'response' => $responseData
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'payment_url' => $responseData['GatewayPageURL']
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Subscription failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function paymentSuccess(Request $request)
    {
        $tranId = $request->tran_id;
        $frontend = config('app.frontend_url');



        if (!$tranId) {
            return redirect($frontend . '/payment-success?status=failed');
        }




        $transaction = DB::table('transactions')
            ->where('id', $tranId)
            ->first();

        if (!$transaction) {
            return redirect($frontend . '/payment-success?status=failed');
        }

        DB::beginTransaction();

        try {

            // ✅ update transaction
            DB::table('transactions')
                ->where('id', $tranId)
                ->update([
                    'status' => 'success',
                    'updated_at' => now(),
                ]);

            // ✅ expire old subscriptions
            DB::table('subscriptions')
                ->where('mfi_id', $transaction->mfi_id)
                ->whereIn('status', ['trial', 'active'])
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

            // ✅ activate new subscription
            DB::table('subscriptions')
                ->where('id', $transaction->subscription_id)
                ->update([
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => now()->addMonth(),
                    'updated_at' => now(),
                ]);

            DB::commit();



            return redirect($frontend . '/payment-success?transactionId=' . $tranId);
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect(
                $frontend . '/payment-success?status=failed&transactionId=' . $tranId
            );
        }
    }

    public function paymentFail(Request $request)
    {
        $tranId = $request->tran_id;

        if ($tranId) {
            DB::table('transactions')
                ->where('id', $tranId)
                ->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);
        }

        // ❌ DO NOT update subscription status here

        $frontend = config('app.frontend_url');

        return redirect(
            $frontend . '/payment-success?status=failed&transactionId=' . $tranId
        );
    }

    public function paymentCancel(Request $request)
    {
        $tranId = $request->tran_id;

        if ($tranId) {
            DB::table('transactions')
                ->where('id', $tranId)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);
        }

        // ❌ DO NOT update subscription status here

        $frontend = config('app.frontend_url');

        return redirect(
            $frontend . '/payment-success?status=cancelled&transactionId=' . $tranId
        );
    }




    public function current(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not linked to MFI'
            ], 400);
        }


        // get latest subscription
        $subscription = DB::table('subscriptions')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.mfi_id', $user->mfi_id)
            ->where(function ($q) {
                $q->whereNull('subscriptions.end_date')
                    ->orWhere('subscriptions.end_date', '>', now());
            })
            ->orderByDesc('subscriptions.created_at')
            ->select(
                'subscriptions.id',
                'subscriptions.status',
                'subscriptions.start_date',
                'subscriptions.end_date', // if exists
                'subscription_plans.name as plan_name',
                'subscription_plans.price_bdt'
            )
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'message' => 'No subscription found',
                'data' => null
            ]);
        }
        $transaction = DB::table('transactions')
            ->where('subscription_id', $subscription->id)
            ->latest('created_at')
            ->first();


        $usage = [
            'approved_loans' => DB::table('loan_applications')
                ->where('mfi_id', $user->mfi_id)
                ->where('status', 'approved')
                ->count(),

            'loan_products' => DB::table('loan_products')
                ->where('mfi_id', $user->mfi_id)
                ->count(),
        ];

        // simple feature logic (your "unique simple idea")

        $features = [
            'can_create_loan_products' => (
                $subscription->plan_name === 'pro' &&
                $subscription->status === 'active'
            ),
            'priority_listing' => (
                $subscription->plan_name === 'pro' &&
                $subscription->status === 'active'
            ),
            'analytics_dashboard' => (
                $subscription->plan_name === 'pro' &&
                $subscription->status === 'active'
            ),
            'more_features_coming' => true
        ];

        return response()->json([
            'success' => true,
            'message' => 'Current subscription',
            'data' => [
                'subscription_id' => $subscription->id,
                'plan_name' => $subscription->plan_name,
                'price_bdt' => $subscription->price_bdt,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'features' => $features,
                'limits' => [
                    'max_approvals' => $subscription->plan_name === 'trial' ? 3 : 'unlimited',
                    'max_products' => $subscription->plan_name === 'trial' ? 2 : 'unlimited',
                ],
                'usage' => $usage,
                'payment' => [
                    'status' => $transaction->status ?? null,
                    'amount' => $transaction->amount ?? null,
                ]
            ]
        ]);
    }

    public function paymentHistory(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not linked to MFI'
            ], 400);
        }

        $payments = DB::table('transactions')
            ->join('subscriptions', 'transactions.subscription_id', '=', 'subscriptions.id')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('transactions.mfi_id', $user->mfi_id)
            ->orderByDesc('transactions.created_at')
            ->select(
                'transactions.id',
                'transactions.amount',
                'transactions.status',
                'transactions.payment_gateway',
                'transactions.created_at',
                'subscription_plans.name as plan_name'
            )
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Payment history',
            'data' => $payments
        ]);
    }


    public function invoice($transactionId, Request $request)
    {
        $user = $request->user();

        $transaction = DB::table('transactions')
            ->join('subscriptions', 'transactions.subscription_id', '=', 'subscriptions.id')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('transactions.id', $transactionId)
            ->where('transactions.mfi_id', $user->mfi_id)
            ->select(
                'transactions.id',
                'transactions.amount',
                'transactions.status',
                'transactions.created_at',
                'subscription_plans.name as plan_name',
                'subscription_plans.price_bdt'
            )
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_id' => 'INV-' . strtoupper(substr($transaction->id, 0, 8)),
                'transaction_id' => $transaction->id,
                'plan_name' => $transaction->plan_name,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'date' => $transaction->created_at
            ]
        ]);
    }

    public function adminPayments()
    {
        $payments = DB::table('transactions')
            ->join('mfi_institutions', 'transactions.mfi_id', '=', 'mfi_institutions.id')
            ->join('subscriptions', 'transactions.subscription_id', '=', 'subscriptions.id')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select(
                'transactions.id',
                'transactions.amount',
                'transactions.status',
                'transactions.created_at',
                'mfi_institutions.name as mfi_name',
                'subscription_plans.name as plan_name'
            )
            ->orderByDesc('transactions.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    public function forceActivate($id)
    {
        $subscription = DB::table('subscriptions')->where('id', $id)->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        DB::beginTransaction();

        try {

            // expire old ones
            DB::table('subscriptions')
                ->where('mfi_id', $subscription->mfi_id)
                ->whereIn('status', ['trial', 'active'])
                ->update([
                    'status' => 'expired',
                    'updated_at' => now()
                ]);

            // activate this
            DB::table('subscriptions')
                ->where('id', $id)
                ->update([
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => now()->addMonth(),
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated manually'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
