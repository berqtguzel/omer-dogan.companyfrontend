<?php

namespace App\Services;

use App\Support\OmrConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\RequestException;

class OmrClient
{
    protected string $base;
    protected int $timeout;
    protected string $tenantId;

    public function __construct()
    {
        $this->base = OmrConfig::baseUrl();
        $this->timeout = (int) config('services.omr.timeout', 10);
        $this->tenantId = OmrConfig::tenantId();
    }

    protected function http()
    {
        return Http::connectTimeout(5)
            ->timeout(20)
            ->withoutVerifying()
            ->acceptJson()
            ->retry(2, 100);
    }


    public function websites(array $query = []): array
    {
        $query = array_filter([
            'tenant_id' => $this->tenantId,
        ] + $query);

        $cacheKey = 'omr.websites.' . md5(json_encode($query));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($query) {
            $res = $this->http()->get("{$this->base}/global/websites", $query);
            if ($res->failed()) {
                $res->throw();
            }
            return $res->json() ?? [];
        });
    }
}
