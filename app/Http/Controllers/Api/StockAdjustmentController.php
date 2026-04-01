<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Stock adjustment controller - index'
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Stock adjustment created'
        ], 201);
    }
}
