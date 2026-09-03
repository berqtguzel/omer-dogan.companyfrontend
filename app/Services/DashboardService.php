<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    protected $apiUrl;
    protected $apiKey;
    protected $siteId;
    protected $version;
    protected $endpoints;
    protected $cacheTtl;
    protected $cachePrefix;

    public function __construct()
    {
        $this->apiUrl = config('dashboard.api_url');
        $this->apiKey = config('dashboard.api_key');
        $this->siteId = config('dashboard.site_id');
        $this->version = config('dashboard.version');
        $this->endpoints = config('dashboard.endpoints');
        $this->cacheTtl = config('dashboard.cache.ttl');
        $this->cachePrefix = config('dashboard.cache.prefix');
    }

    protected function getEndpoint($name)
    {
        $endpoint = $this->endpoints[$name] ?? '';
        return str_replace('{site_id}', $this->siteId, $endpoint);
    }

    protected function makeRequest($method, $endpoint, $data = null, $useCache = true)
    {
        $cacheKey = $this->cachePrefix . md5($method . $endpoint . json_encode($data));

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(20)
                ->retry(1, 50) // ⚡ 1 retry, 50ms bekleme (daha hızlı)
                ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'X-Site-ID' => $this->siteId
            ])->$method($this->apiUrl . '/' . $this->version . $endpoint, $data);

            if ($response->successful()) {
                $result = $response->json();
                if ($useCache) {
                    Cache::put($cacheKey, $result, $this->cacheTtl);
                }
                return $result;
            }

            Log::error('Dashboard API Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Dashboard API Exception', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }


    public function getContent($section = null)
    {
        $endpoint = $this->getEndpoint('content');
        if ($section) {
            $endpoint .= "/{$section}";
        }
        return $this->makeRequest('get', $endpoint);
    }

    public function getServices()
    {
        return $this->makeRequest('get', $this->getEndpoint('services'));
    }

    public function getLocations()
    {
        return $this->makeRequest('get', $this->getEndpoint('locations'));
    }


    public function submitContact($data)
    {
        return $this->makeRequest('post', $this->getEndpoint('contacts'), $data, false);
    }

    public function getSettings()
    {
        return $this->makeRequest('get', $this->getEndpoint('settings'));
    }

    public function getAnalytics($period = 'week')
    {
        return $this->makeRequest(
            'get',
            $this->getEndpoint('analytics'),
            ['period' => $period],
            false
        );
    }

    public function clearCache()
    {
        $keys = Cache::get($this->cachePrefix . '*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
