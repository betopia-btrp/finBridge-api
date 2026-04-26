<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanProductController extends Controller
{
    public function store(Request $request)
    {
        // validation
        $request->validate([
            'name' => 'required|string|max:255',
            'max_amount' => 'required|numeric|min:1',
            'min_amount' => 'nullable|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0',
            'processing_fee' => 'nullable|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);



        $user = $request->user();

        // 🔒 subscription check
        $subscription = $this->getActiveSubscription($user->mfi_id);

        if (!$subscription || now()->gt($subscription->end_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Trial expired. Upgrade to continue.',
                'data' => null
            ], 403);
        }

        // role + tenant check (combined clean)
        if ($user->role !== 'mfi_admin' || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only MFI admin can create loan products',
                'data' => null
            ], 403);
        }

        if ($request->min_amount && $request->min_amount > $request->max_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Min amount cannot be greater than max amount',
                'data' => null
            ], 400);
        }
        // generate ID
        $id = Str::uuid();

        DB::beginTransaction();

        try {
            DB::table('loan_products')->insert([
                'id' => $id,
                'mfi_id' => $user->mfi_id,
                'name' => trim($request->name),
                'description' => $request->description,
                'min_amount' => $request->min_amount,
                'max_amount' => $request->max_amount,
                'interest_rate' => $request->interest_rate,
                'processing_fee' => $request->processing_fee,
                'duration_months' => $request->duration_months,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Loan product created',
                'data' => [
                    'product_id' => $id
                ]
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create loan product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $products = DB::table('loan_products')
            ->join('mfi_institutions', 'loan_products.mfi_id', '=', 'mfi_institutions.id')
            ->where('loan_products.status', 'active')
            ->select(
                'loan_products.id',
                'loan_products.mfi_id',
                'loan_products.name',
                'loan_products.description',
                'loan_products.min_amount',
                'loan_products.max_amount',
                'loan_products.interest_rate',
                'loan_products.processing_fee',
                'loan_products.duration_months',
                'mfi_institutions.name as mfi_name'
            )
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Loan products fetched',
            'data' => $products
        ]);
    }

    private function getActiveSubscription($mfiId)
    {
        return DB::table('subscriptions')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.mfi_id', $mfiId)
            ->whereIn('subscriptions.status', ['trial', 'active'])
            ->orderByDesc('subscriptions.created_at')
            ->first();
    }
}
