<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterEntrepreneurRequest;
use App\Http\Requests\RegisterMfiRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function registerMfi(RegisterMfiRequest $request)
    {
        DB::beginTransaction();

        try {
            // 1. Create User
            $user = User::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => $request->password, // hashed via cast
                'role' => 'mfi_admin',
                'status' => 'active',
            ]);

            // 2. Create MFI
            $mfiId = Str::uuid();

            DB::table('mfi_institutions')->insert([
                'id' => $mfiId,
                'name' => $request->mfi_name,
                'email' => $request->mfi_email,
                'phone' => $request->mfi_phone,
                'owner_id' => $user->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2.1 Link user to MFI
            $user->update([
                'mfi_id' => $mfiId
            ]);

            // 3. Get Trial Plan
            $trialPlan = DB::table('subscription_plans')
                ->where('name', 'trial')
                ->first();

            if (!$trialPlan) {
                throw new \Exception('Trial plan not found. Please seed data.');
            }




            // 4. Create Trial Subscription (10 days)
            DB::table('subscriptions')->insert([
                'id' => Str::uuid(),
                'mfi_id' => $mfiId,
                'plan_id' => $trialPlan->id,
                'start_date' => now(),
                'end_date' => now()->addDays(10), // ✅ FIXED
                'status' => 'trial',              // ✅ keep as trial
                'created_at' => now(),
                'updated_at' => now(),
            ]);



            // 4. Create Token
            $token = $user->createToken('api_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'MFI registered successfully',
                'data' => [
                    'user' => $user,
                    'mfi_id' => $mfiId,
                    'token' => $token
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'data' => null,
                'errors' => [
                    'system' => [$e->getMessage()]
                ]
            ], 500);
        }
    }



    public function registerEntrepreneur(RegisterEntrepreneurRequest $request)
    {
        try {
            // 1. Create User
            $user = User::create([
                'id' => Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => $request->password,
                'role' => 'entrepreneur',
                'status' => 'active',
            ]);

            // 2. Create Token
            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Entrepreneur registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'data' => null,
                'errors' => [
                    'system' => [$e->getMessage()]
                ]
            ], 500);
        }
    }


    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            // ❌ Invalid credentials
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'data' => null,
                    'errors' => [
                        'auth' => ['Email or password is incorrect']
                    ]
                ], 401);
            }

            // 🔒 Optional: block inactive users
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Account inactive',
                    'data' => null,
                    'errors' => [
                        'auth' => ['Your account is not active']
                    ]
                ], 403);
            }

            // 🔑 Create token
            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'data' => null,
                'errors' => [
                    'system' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function logout(\Illuminate\Http\Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
            'data' => null
        ]);
    }
}
