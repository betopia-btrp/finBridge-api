<?php

namespace App\Http\Controllers\Api;

use App\Helpers\SubscriptionHelper;
use App\Http\Controllers\Controller;
use App\Mail\LoanApplicationSubmitted;
use App\Mail\LoanApproved;
use App\Mail\LoanRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    public function apply(Request $request)
    {

        if ($request->has('data')) {
            $data = json_decode($request->data, true);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON data',
                    'data' => null
                ], 400);
            }

            // merge into request
            $request->merge($data);
        }

        $request->validate([
            'mfi_id' => 'required|uuid',
            'loan_product_id' => 'required|uuid',
            'amount' => 'required|numeric|min:1',
            'duration_months' => 'required|integer|min:1',
            'nid' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'tax' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'tin' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $user = $request->user();

        // ensure entrepreneur only
        if ($user->role !== 'entrepreneur') {
            return response()->json([
                'success' => false,
                'message' => 'Only entrepreneurs can apply',
                'data' => null
            ], 403);
        }

        // prevent duplicate application
        $existing = DB::table('loan_applications')
            ->where('user_id', $user->id)
            ->where('loan_product_id', $request->loan_product_id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied for this loan product',
                'data' => null
            ], 400);
        }

        // validate product
        $product = DB::table('loan_products')
            ->where('id', $request->loan_product_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid loan product',
                'data' => null
            ], 400);
        }

        // ensure product belongs to MFI
        if ($product->mfi_id !== $request->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid MFI and product mismatch',
                'data' => null
            ], 400);
        }

        $applicationId = Str::uuid();

        DB::beginTransaction();

        try {
            // create application
            DB::table('loan_applications')->insert([
                'id' => $applicationId,
                'user_id' => $user->id,
                'mfi_id' => $request->mfi_id,
                'loan_product_id' => $request->loan_product_id,
                'amount' => $request->amount,
                'duration_months' => $request->duration_months,
                'purpose' => $request->purpose,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // NID (required)
            $nidPath = $request->file('nid')->store('documents', 'public');

            DB::table('application_documents')->insert([
                'id' => Str::uuid(),
                'loan_application_id' => $applicationId,
                'type' => 'nid',
                'file_path' => $nidPath,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // tax (optional)
            if ($request->hasFile('tax')) {
                $path = $request->file('tax')->store('documents', 'public');

                DB::table('application_documents')->insert([
                    'id' => Str::uuid(),
                    'loan_application_id' => $applicationId,
                    'type' => 'tax',
                    'file_path' => $path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // tin (optional)
            if ($request->hasFile('tin')) {
                $path = $request->file('tin')->store('documents', 'public');

                DB::table('application_documents')->insert([
                    'id' => Str::uuid(),
                    'loan_application_id' => $applicationId,
                    'type' => 'tin',
                    'file_path' => $path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            Mail::to($user->email)->send(
                new LoanApplicationSubmitted((object)[
                    'id' => $applicationId,
                    'amount' => $request->amount,
                    'duration_months' => $request->duration_months,
                    'user_name' => $user->name,
                ])
            );

            return response()->json([
                'success' => true,
                'message' => 'Loan application submitted',
                'data' => [
                    'application_id' => $applicationId
                ]
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Application failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function mfiApplications(Request $request)
    {
        $user = $request->user();

        // ensure MFI admin
        if ($user->role !== 'mfi_admin' || !$user->mfi_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = DB::table('loan_applications')
            ->join('users', 'loan_applications.user_id', '=', 'users.id')
            ->join('loan_products', 'loan_applications.loan_product_id', '=', 'loan_products.id')
            ->where('loan_applications.mfi_id', $user->mfi_id);

        // ✅ FILTER: status
        if ($request->has('status')) {
            $query->where('loan_applications.status', $request->status);
        }

        // ✅ SEARCH: applicant name
        if ($request->has('search')) {
            $query->where('users.name', 'like', '%' . $request->search . '%');
        }

        $applications = $query
            ->select(
                'loan_applications.id',
                'users.name as applicant_name',
                'users.email',
                'loan_products.name as product_name',
                'loan_applications.amount',
                'loan_applications.duration_months',
                'loan_applications.status',
                'loan_applications.created_at'
            )
            ->orderByDesc('loan_applications.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'MFI applications fetched',
            'data' => $applications
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            $query = DB::table('loan_applications')
                ->join('users', 'loan_applications.user_id', '=', 'users.id')
                ->join('loan_products', 'loan_applications.loan_product_id', '=', 'loan_products.id')
                ->where('loan_applications.id', $id);

            // role-based access
            if ($user->role === 'mfi_admin') {
                $query->where('loan_applications.mfi_id', $user->mfi_id);
            } elseif ($user->role === 'entrepreneur') {
                $query->where('loan_applications.user_id', $user->id);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'data' => null
                ], 403);
            }

            $application = $query->select(
                'loan_applications.id',
                'users.name as applicant_name',
                'users.email',
                'loan_products.name as product_name',
                'loan_applications.amount',
                'loan_applications.duration_months',
                'loan_applications.purpose',
                'loan_applications.status',
                'loan_applications.created_at'
            )->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found',
                    'data' => null
                ], 404);
            }

            // documents (SAFE)
            $documentsRaw = DB::table('application_documents')
                ->where('loan_application_id', $id)
                ->get();

            $documents = [];

            foreach ($documentsRaw as $doc) {
                $documents[] = [
                    'type' => $doc->type,
                    'file_path' => $doc->file_path,
                    'url' => url('storage/' . $doc->file_path),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Application details fetched',
                'data' => [
                    'application' => $application,
                    'documents' => $documents
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch application',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function approve(Request $request, $id)
    {
        $user = $request->user();

        // ✅ role first
        if ($user->role !== 'mfi_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // ✅ subscription
        $subscription = $request->attributes->get('subscription');

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription missing'
            ], 403);
        }

        // ✅ trial limit
        if ($subscription->plan_name === 'trial') {

            $count = DB::table('loan_applications')
                ->where('mfi_id', $user->mfi_id)
                ->whereIn('status', ['approved', 'rejected'])
                ->count();

            if ($count >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trial limit reached (3 actions). Upgrade to Pro.'
                ], 403);
            }
        }



        $application = DB::table('loan_applications')
            ->where('id', $id)
            ->where('mfi_id', $user->mfi_id)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
                'data' => null
            ], 404);
        }

        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Already processed',
                'data' => null
            ], 400);
        }

        DB::table('loan_applications')
            ->where('id', $id)
            ->update(['status' => 'approved']);

        $userData = DB::table('users')
            ->where('id', $application->user_id)
            ->first();

        if (!$userData) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        try {
            Mail::to($userData->email)->send(
                new LoanApproved((object)[
                    'id' => $application->id,
                    'amount' => $application->amount,
                    'user_name' => $userData->name,
                ])
            );
        } catch (\Exception $e) {
            \Log::error('Mail failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Application approved',
        ]);
    }



    public function reject(Request $request, $id)
    {
        $user = $request->user();

        // ✅ role first
        if ($user->role !== 'mfi_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // ✅ subscription
        $subscription = $request->attributes->get('subscription');

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription missing'
            ], 403);
        }

        // ✅ trial limit
        if ($subscription->plan_name === 'trial') {

            $count = DB::table('loan_applications')
                ->where('mfi_id', $user->mfi_id)
                ->whereIn('status', ['approved', 'rejected'])
                ->count();

            if ($count >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trial limit reached (3 actions). Upgrade to Pro.'
                ], 403);
            }
        }


        // 🔍 find application
        $application = DB::table('loan_applications')
            ->where('id', $id)
            ->where('mfi_id', $user->mfi_id)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
                'data' => null
            ], 404);
        }

        // 🚫 prevent re-processing
        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Already processed',
                'data' => null
            ], 400);
        }

        // ❌ update status
        DB::table('loan_applications')
            ->where('id', $id)
            ->update([
                'status' => 'rejected',
                'updated_at' => now()
            ]);

        // 👤 get user
        $userData = DB::table('users')
            ->where('id', $application->user_id)
            ->first();

        if (!$userData) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        // 📧 send email safely
        try {
            Mail::to($userData->email)->send(
                new LoanRejected((object)[
                    'id' => $application->id,
                    'user_name' => $userData->name,
                ])
            );
        } catch (\Exception $e) {
            \Log::error('Reject mail failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Application rejected',

        ]);
    }


    public function myApplications(Request $request)
    {
        $user = $request->user();

        // ensure entrepreneur only
        if ($user->role !== 'entrepreneur') {
            return response()->json([
                'success' => false,
                'message' => 'Only entrepreneurs can access this',
            ], 403);
        }

        $applications = DB::table('loan_applications')
            ->join('loan_products', 'loan_applications.loan_product_id', '=', 'loan_products.id')
            ->join('mfi_institutions', 'loan_applications.mfi_id', '=', 'mfi_institutions.id')
            ->where('loan_applications.user_id', $user->id)
            ->select(
                'loan_applications.id',
                'loan_products.name as product_name',
                'mfi_institutions.name as mfi_name',
                'loan_applications.amount',
                'loan_applications.duration_months',
                'loan_applications.status',
                'loan_applications.created_at'
            )
            ->orderByDesc('loan_applications.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Your applications fetched',
            'data' => $applications
        ]);
    }

    public function adminAll()
    {
        $applications = DB::table('loan_applications')
            ->join('users', 'loan_applications.user_id', '=', 'users.id')
            ->join('mfi_institutions', 'loan_applications.mfi_id', '=', 'mfi_institutions.id')
            ->select(
                'loan_applications.id',
                'loan_applications.amount',
                'loan_applications.status',
                'loan_applications.created_at',
                'users.name as borrower_name',
                'mfi_institutions.name as mfi_name'
            )
            ->orderByDesc('loan_applications.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }
}
