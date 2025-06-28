<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\News;
use App\Models\Alert;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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

    // Test all the API calls in one job
    // This is just for testing purposes, not for production use
    public function testJob(): void
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
                        'float' => $float,
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

    public function testNewsJob(): void
    {
        $apiKey = config('services.finnhub.api_key');
        $url = "https://finnhub.io/api/v1/news?category=general&token={$apiKey}";

        $response = Http::get($url);

        if ($response->ok()) {
            foreach ($response->json() as $news) {
                News::updateOrCreate([
                    'external_id' => $news['id'] ?? md5($news['headline'])
                ], [
                    'symbol' => $news['related'] ?? 'MARKET',
                    'title' => $news['headline'],
                    'content' => $news['summary'],
                    'source' => $news['source'],
                    'sentiment' => 'yellow', // Simplified: later we can use NLP from Finnhub
                    'published_at' => $news['datetime'] ? now()->createFromTimestampMs($news['datetime']) : now()
                ]);
            }
        }
    }

    public function testAlertJob()
    {
        $stocks = Stock::all();

        foreach ($stocks as $stock) {

            $symbol = $stock->symbol;
            $price = $stock->price;
            
            $cacheKey = "momentum_{$symbol}";
            $lastHigh = Cache::get($cacheKey);
            Log::info("Checking momentum for {$symbol}: Current Price: {$price}, Last High: {$lastHigh}");
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
                Cache::put($cacheKey, $price, now()->addSeconds(300)); 
            }
        }
    }

    public function testQuote()
    {
        $symbols = ['AAPL', 'TSLA', 'MSFT'];
        $response = Http::get('https://app.quotemedia.com/data/getSnapQuotes.json', [
            'sid' => '37a5699d-207c-41db-8780-d3d6a66998c7',
            'webmasterId' => 501,
            'symbols' => implode(',', $symbols),
        ]);
        if ($response->ok()) {
            $quotes = $response->json();
            $quotedata = $quotes['quotedata'] ?? [];
            if (empty($quotedata)) {
                Log::error("No quote data found in response: " . json_encode($quotes, JSON_PRETTY_PRINT));
                return response()->json(['error' => 'No quote data found'], 404);
            }
            foreach ($quotedata as $data) {
                $symbol = $data['symbol'] ?? '';
                $price = $data['pricedata']['last'] ?? null;
                $volume = $data['pricedata']['tradevolume'] ?? null;
                $gap = $data['pricedata']['changepercent'] ?? null;
                $change = $data['pricedata']['change'] ?? null;
                $float = $data['pricedata']['float'] ?? null; // Not avialable in this API
                $relVol = $data['pricedata']['relativeVolume'] ?? null; // Not available in this API


                // Update or create stock record
                Stock::updateOrCreate(
                    ['symbol' => $symbol],
                    [
                        'price' => $price,
                        'gap_percent' => $gap,
                        'volume' => $volume,
                        'fetched_at' => now(),
                        'float' => $float, // Not available in this API
                        'relative_volume' => $relVol, // Not available in this API
                        'short_interest' => null, // Not available in this API
                    ]
                );
                Log::info("Updated stock data for {$symbol}: Price: {$price}, Volume: {$volume}, Gap: {$gap}, Change: {$change}, Float: {$float}, Relative Volume: {$relVol}");
                // Process each symbol's data
                // Log::info("Quote for {$symbol}: " . json_encode($data, JSON_PRETTY_PRINT));
            }
            Log::info("Fetched quotes: " . json_encode($quotes, JSON_PRETTY_PRINT));
            return response()->json($quotes);
        } else {
            Log::error("Failed to fetch quotes: " . $response->body());
            return response()->json(['error' => 'Failed to fetch quotes'], 500);
        }
    }

}
