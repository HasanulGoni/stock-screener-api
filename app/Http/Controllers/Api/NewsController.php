<?php

namespace App\Http\Controllers\Api;

use App\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NewsController extends Controller
{
    public function forStock($symbol)
    {
        $stock = Stock::where('symbol', strtoupper($symbol))->firstOrFail();
        $news = $stock->news()->latest('published_at')->get();
        return response()->json($news);
    }
}
