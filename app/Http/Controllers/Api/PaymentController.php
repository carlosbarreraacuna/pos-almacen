<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Payment controller - index'
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Payment created'
        ], 201);
    }
}
