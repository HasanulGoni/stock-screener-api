<?php

namespace App\Http\Controllers\Api;

use App\Models\Alert;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = Alert::with('stock')->latest('triggered_at')->limit(50)->get();
        return response()->json($alerts);
    }
}
