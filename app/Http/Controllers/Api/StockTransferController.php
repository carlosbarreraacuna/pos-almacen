<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Stock transfer controller - index'
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Stock transfer created'
        ], 201);
    }
}
