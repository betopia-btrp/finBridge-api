<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalMfis = DB::table('mfi_institutions')->count();

        $activeMfis = DB::table('subscriptions')
            ->where('status', 'active')
            ->distinct('mfi_id')
            ->count('mfi_id');

        $totalUsers = DB::table('users')->count();

        $totalLoans = DB::table('loan_applications')->count();

        $totalRevenue = DB::table('transactions')
            ->where('status', 'success')
            ->sum('amount');

        $activeSubscriptions = DB::table('subscriptions')
            ->where('status', 'active')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_mfis' => $totalMfis,
                'active_mfis' => $activeMfis,
                'total_users' => $totalUsers,
                'total_loans' => $totalLoans,
                'total_revenue' => $totalRevenue,
                'active_subscriptions' => $activeSubscriptions
            ]
        ]);
    }

    public function revenueReport(Request $request)
    {
        // optional filters
        $from = $request->query('from'); // YYYY-MM-DD
        $to = $request->query('to');

        // base query (ONLY SUCCESS PAYMENTS)
        $baseQuery = DB::table('transactions')
            ->where('status', 'success');

        if ($from && $to) {
            $baseQuery->whereBetween('created_at', [$from, $to]);
        }

        // 💰 total revenue
        $totalRevenue = (clone $baseQuery)->sum('amount');

        // 📅 today revenue
        $todayRevenue = DB::table('transactions')
            ->where('status', 'success')
            ->whereDate('created_at', today())
            ->sum('amount');

        // 📆 this month revenue
        $monthRevenue = DB::table('transactions')
            ->where('status', 'success')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // 📊 last 7 days trend
        $trend = DB::table('transactions')
            ->selectRaw("DATE(created_at) as date, SUM(amount) as total")
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'today_revenue' => $todayRevenue,
                'monthly_revenue' => $monthRevenue,
                'trend_last_7_days' => $trend
            ]
        ]);
    }
}
