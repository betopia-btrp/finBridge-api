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

            DB::table('subscriptions')
                ->where('mfi_id', $user->mfi_id)
                ->where('status', 'trial')
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

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

            // 3. call SSL
            $response = Http::asForm()->post(
                'https://sandbox.sslcommerz.com/gwprocess/v4/api.php',
                [
                    'store_id' => env('SSL_STORE_ID'),
                    'store_passwd' => env('SSL_STORE_PASSWORD'),
                    'total_amount' => $plan->price_bdt,
                    'currency' => 'BDT',
                    'tran_id' => $transactionId,

                    'success_url' => 'http://localhost:3000/payment-success?transactionId=' . $transactionId,
                    'fail_url' => 'http://localhost:3000/payment-fail?transactionId=' . $transactionId,
                    'cancel_url' => 'http://localhost:3000/payment-cancel?transactionId=' . $transactionId,

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

        $transaction = DB::table('transactions')
            ->where('id', $tranId)
            ->first();

        if (!$transaction) {
            return redirect('http://localhost:3000/payment-error');
        }

        DB::beginTransaction();

        try {
            // update transaction
            DB::table('transactions')
                ->where('id', $tranId)
                ->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $request->bank_tran_id ?? null,
                    'updated_at' => now(),
                ]);

            // expire old
            DB::table('subscriptions')
                ->where('mfi_id', $transaction->mfi_id)
                ->whereIn('status', ['trial', 'active'])
                ->update([
                    'status' => 'expired',
                    'updated_at' => now(),
                ]);

            // activate new
            DB::table('subscriptions')
                ->where('id', $transaction->subscription_id)
                ->update([
                    'status' => 'active',
                    'end_date' => now()->addMonth(),
                    'updated_at' => now(),
                ]);

            DB::commit();

            // ✅ REDIRECT TO FRONTEND
            return redirect('http://localhost:3000/payment-success');
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect('http://localhost:3000/payment-error');
        }
    }

    public function paymentFail(Request $request)
    {
        $tranId = $request->tran_id;

        DB::table('transactions')
            ->where('id', $tranId)
            ->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);

        DB::table('subscriptions')
            ->where('id', function ($q) use ($tranId) {
                $q->select('subscription_id')
                    ->from('transactions')
                    ->where('id', $tranId)
                    ->limit(1);
            })
            ->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);

        return redirect('http://localhost:3000/payment-fail');
    }

    public function paymentCancel(Request $request)
    {
        $tranId = $request->tran_id;

        DB::table('transactions')
            ->where('id', $tranId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        DB::table('subscriptions')
            ->where('id', function ($q) use ($tranId) {
                $q->select('subscription_id')
                    ->from('transactions')
                    ->where('id', $tranId)
                    ->limit(1);
            })
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        return redirect('http://localhost:3000/payment-cancel');
    }

    public function confirmPayment(Request $request)
    {
        $transactionId = $request->transaction_id;

        DB::beginTransaction();

        try {
            $transaction = DB::table('transactions')
                ->where('id', $transactionId)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid transaction'
                ], 404);
            }

            // update transaction
            DB::table('transactions')
                ->where('id', $transactionId)
                ->update([
                    'status' => 'success',
                    'updated_at' => now(),
                ]);

            // activate subscription
            DB::table('subscriptions')
                ->where('id', $transaction->subscription_id)
                ->update([
                    'status' => 'active',
                    'end_date' => now()->addMonth(),
                    'updated_at' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed',
                'error' => $e->getMessage()
            ]);
        }
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

        // simple feature logic (your "unique simple idea")
        $features = [
            'can_create_loan_products' => $subscription->plan_name === 'pro',
            'priority_listing' => $subscription->plan_name === 'pro',
            'analytics_dashboard' => $subscription->plan_name === 'pro',
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
                'features' => $features
            ]
        ]);
    }

    public function upgrade(Request $request)
    {
        $user = $request->user();

        if (!$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'User not linked to MFI',
                'data' => null
            ], 400);
        }

        // get PRO plan
        $proPlan = DB::table('subscription_plans')
            ->where('name', 'pro')
            ->first();

        if (!$proPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Pro plan not found',
                'data' => null
            ], 404);
        }

        DB::beginTransaction();

        try {
            // 1. expire old subscription
            DB::table('subscriptions')
                ->where('mfi_id', $user->mfi_id)
                ->whereIn('status', ['trial', 'active'])
                ->update([
                    'status' => 'expired',
                    'updated_at' => now()
                ]);

            // 2. create new PRO subscription
            DB::table('subscriptions')->insert([
                'id' => Str::uuid(),
                'mfi_id' => $user->mfi_id,
                'plan_id' => $proPlan->id,
                'start_date' => now(),
                'end_date' => now()->addMonth(), // simple monthly
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Upgraded to Pro successfully',
                'data' => null
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Upgrade failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
