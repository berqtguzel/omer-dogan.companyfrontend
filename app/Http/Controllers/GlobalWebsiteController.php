<?php

namespace App\Http\Controllers;

use App\Support\OmrConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GlobalWebsiteController extends Controller
{

    public static function getGlobalWebsites(): array
    {
        $cacheKey = 'global_websites';


        return Cache::remember($cacheKey, now()->addDays(7), function () {
            try {
                $baseUrl = OmrConfig::baseUrl();
                $url = $baseUrl . '/global/websites';

                Log::info("🌍 Fetching Global Websites => " . $url);

                $response = Http::withoutVerifying()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->get($url);

                if (!$response->ok()) {
                    Log::error("❌ Global Websites API request failed", [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    return [];
                }

                $json = $response->json();

                if (isset($json['data']) && is_array($json['data'])) {
                    return $json['data'];
                }

                Log::warning("⚠ Unexpected API format", $json);

                return is_array($json) ? $json : [];

            } catch (\Throwable $e) {
                Log::error("🚨 GlobalWebsiteController ERROR: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return [];
            }
        });
    }
}
