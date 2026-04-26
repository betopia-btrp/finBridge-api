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

        // ✅ role check FIRST
        if ($user->role !== 'mfi_admin' || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only MFI admin can create loan products',
            ], 403);
        }

        // ✅ subscription from middleware
        $subscription = $request->attributes->get('subscription');

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription missing'
            ], 403);
        }

        // ✅ trial limit
        if ($subscription->plan_name === 'trial') {

            $count = DB::table('loan_products')
                ->where('mfi_id', $user->mfi_id)
                ->count();

            if ($count >= 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trial allows only 2 loan products. Upgrade to Pro.'
                ], 403);
            }
        }

        // validation logic
        if ($request->min_amount && $request->min_amount > $request->max_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Min amount cannot be greater than max amount',
            ], 400);
        }

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

    public function myProducts(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'mfi_admin' || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $products = DB::table('loan_products')
            ->where('mfi_id', $user->mfi_id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Your loan products',
            'data' => $products
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role !== 'mfi_admin' || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $product = DB::table('loan_products')
            ->where('id', $id)
            ->where('mfi_id', $user->mfi_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'max_amount' => 'sometimes|numeric|min:1',
            'min_amount' => 'nullable|numeric|min:0',
            'interest_rate' => 'sometimes|numeric|min:0',
            'processing_fee' => 'nullable|numeric|min:0',
            'duration_months' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
        ]);

        DB::table('loan_products')
            ->where('id', $id)
            ->update([
                'name' => $request->name ?? $product->name,
                'description' => $request->description ?? $product->description,
                'min_amount' => $request->min_amount ?? $product->min_amount,
                'max_amount' => $request->max_amount ?? $product->max_amount,
                'interest_rate' => $request->interest_rate ?? $product->interest_rate,
                'processing_fee' => $request->processing_fee ?? $product->processing_fee,
                'duration_months' => $request->duration_months ?? $product->duration_months,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan product updated'
        ]);
    }

    public function delete(Request $request, $id)
    {

        \Log::info('DELETE HIT', [
            'user' => $request->user()
        ]);

        $user = $request->user();

        if ($user->role !== 'mfi_admin' || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $product = DB::table('loan_products')
            ->where('id', $id)
            ->where('mfi_id', $user->mfi_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        DB::table('loan_products')
            ->where('id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Loan product deleted'
        ]);
    }
}
