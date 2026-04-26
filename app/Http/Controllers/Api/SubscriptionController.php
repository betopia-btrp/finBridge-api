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
        $existing = DB::table('subscriptions')
            ->where('mfi_id', $user->mfi_id)
            ->whereIn('status', ['pending_payment', 'active'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Existing subscription found'
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

    public function paymentFail()
    {
        return redirect('http://localhost:3000/payment-fail');
    }

    public function paymentCancel()
    {
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
