<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Warehouse controller - index'
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Warehouse created'
        ], 201);
    }
}
