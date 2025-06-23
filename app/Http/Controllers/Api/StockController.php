<?php

namespace App\Http\Controllers\Api;

use App\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StockController extends Controller
{
    public function index()
    {
        $stocks = Stock::latest()->paginate(20);
        return response()->json($stocks);
    }

    public function show($symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->firstOrFail();
        return response()->json($stock);
    }

    public function test(){
        return response()->json(['message' => 'Test endpoint is working!']);
    }
}
