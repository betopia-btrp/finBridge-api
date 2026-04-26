<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class MfiController extends Controller
{
    public function index()
    {
        $mfis = DB::table('mfi_institutions')
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'phone')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'MFI list fetched',
            'data' => $mfis
        ]);
    }
}
