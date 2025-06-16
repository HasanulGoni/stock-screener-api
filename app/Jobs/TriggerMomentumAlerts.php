<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Alert;
use App\Models\Stock;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TriggerMomentumAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $stocks = Stock::all();

        foreach ($stocks as $stock) {
            $symbol = $stock->symbol;
            $price = $stock->price;

            $cacheKey = "momentum_{$symbol}";
            $lastHigh = Cache::get($cacheKey);

            if ($lastHigh && abs($price - $lastHigh) <= 0.01) {
                // Price hit all-time high again within threshold
                Alert::create([
                    'symbol' => $symbol,
                    'type' => 'momentum',
                    'message' => "{$symbol} hit a new high again at \${$price}",
                    'triggered_at' => Carbon::now(),
                ]);
            }

            if (!$lastHigh || $price > $lastHigh) {
                // Update the all-time high in cache for this symbol
                Cache::put($cacheKey, $price, now()->addSeconds(2));
            }
        }
    }
}
