<?php

use App\Services\OmrClient;

if (! function_exists('omr')) {
    function omr(): OmrClient {
        return app(OmrClient::class);
    }
}

if (! function_exists('omr_websites')) {

    function omr_websites(array $query = []): array {
        return omr()->websites($query);
    }
}

if (! function_exists('mirror_media')) {
    function mirror_media(mixed $data): mixed
    {
        return app(\App\Services\MediaMirrorService::class)->transform($data);
    }
}
