<?php

namespace App\Jobs;

use App\Models\Stock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FetchStockData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $apiKey = config('services.polygon.api_key');
        Log::info('🚀 FetchStockDataJob started with API key: ' . $apiKey);
        $url = "https://api.polygon.io/v2/snapshot/locale/us/markets/stocks/tickers?apiKey={$apiKey}";

        Log::info('🚀 FetchStockDataJob started fetching data from Polygon API');
        $response = Http::get($url);

        if ($response->ok()) {
            Log::info('🚀 FetchStockDataJob fetched data successfully');
            foreach ($response['tickers'] as $data) {
                Stock::updateOrCreate([
                    'symbol' => $data['ticker']
                ], [
                    'price' => $data['lastTrade']['p'] ?? null,
                    'volume' => $data['day']['v'] ?? null,
                    'float' => null, // Not provided by Polygon
                    'gap_percent' => $data['todaysChangePerc'] ?? null,
                    'relative_volume' => null, // You can compute this
                    'short_interest' => null, // Needs alternative source
                    'close_percent' => $data['todaysChangePerc'] ?? null
                ]);
            }
        }else {
            Log::error('🚨 FetchStockDataJob failed to fetch data: ' . $response->body());
        }
    }
}
