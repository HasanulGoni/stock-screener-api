<?php

namespace App\Jobs;

use App\Models\News;
use App\Models\Stock;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FetchNewsData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $apiKey = config('services.finnhub.key');
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
}
