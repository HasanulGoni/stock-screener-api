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
        Log::info("Fetching stock data...");
        $symbols = ['AAPL', 'TSLA', 'MSFT'];

        foreach ($symbols as $symbol) {
            // Get price & gap % from Finnhub
            $finnhub = Http::get('https://finnhub.io/api/v1/quote', [
                'symbol' => $symbol,
                'token' => config('services.finnhub.api_key'),
            ]);

            // Get volume from Twelve Data
            $twelve = Http::get('https://api.twelvedata.com/time_series', [
                'symbol' => $symbol,
                'interval' => '1min',
                'outputsize' => 2,
                'apikey' => config('services.twelvedata.api_key'),
            ]);

            // --- 3. Finnhub Profile (for Float) ---
            $profileResponse = Http::get("https://finnhub.io/api/v1/stock/profile2", [
                'symbol' => $symbol,
                'token' => config('services.finnhub.api_key')
            ]);


            // --- 4. Finnhub Metrics (for Avg Vol) ---
            $metricsResponse = Http::get("https://finnhub.io/api/v1/stock/metric", [
                'symbol' => $symbol,
                'metric' => 'all',
                'token' => config('services.finnhub.api_key')
            ]);
            
            if ($finnhub->successful() && $twelve->successful() && $profileResponse->successful() && $metricsResponse->successful()) {
                $fData = $finnhub->json();
                $tData = $twelve->json();
                $profile = $profileResponse->json();
                // Log::info("Finnhub Profile for {$symbol}: " . json_encode($profile, JSON_PRETTY_PRINT)); // Log the profile response
                $metrics = $metricsResponse->json();
                // Log::info("Finnhub Metrics for {$symbol}: " . json_encode($metrics, JSON_PRETTY_PRINT)); // Log the metrics response

                $volume = $tData['values'][0]['volume'] ?? null;
                $price = $fData['c'] ?? null;
                $prevClose = $fData['pc'] ?? null;
                $gap = $fData['dp'] ?? null;
                // $change = $fData['d'] ?? null;

                $float = $profile['shareOutstanding'] ?? null;

                
                $avgVol = $metrics['metric']['10DayAverageTradingVolume'] ?? null;
                $relVol = ($volume && $avgVol) ? round($volume / $avgVol, 2) : null;



                Stock::updateOrCreate(
                    ['symbol' => $symbol],
                    [
                        'price' => $price,
                        'close_price' => $prevClose,
                        'gap_percent' => $gap,
                        'volume' => $volume,
                        'fetched_at' => now(),
                        'float' => $float, // Add later when possible
                        'relative_volume' => $relVol,
                        'short_interest' => null,
                    ]
                );
                
            } else {
                Log::error("Failed to fetch data for {$symbol}. Finnhub: {$finnhub->status()}, Twelve Data: {$twelve->status()}, Profile: {$profileResponse->status()}, Metrics: {$metricsResponse->status()}");
                continue; // Skip to the next symbol if any request fails
            }
        }

        Log::info("✅ Fetched stock data for: " . implode(', ', $symbols));
    }

}
