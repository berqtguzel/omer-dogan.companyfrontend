<?php

namespace App\Http\Controllers;

use App\Services\OmrAutomaticWarmup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OmrCacheWebhookController extends Controller
{
    public function __invoke(Request $request, OmrAutomaticWarmup $warmup): JsonResponse
    {
        $configuredSecret = (string) config('omr_warmup.webhook_secret');
        $providedSecret = (string) $request->header('X-OMR-Warmup-Secret');

        abort_if($configuredSecret === '', 404);
        abort_unless(hash_equals($configuredSecret, $providedSecret), 403);

        $warmup->invalidate();

        return response()->json([
            'ok' => true,
            'message' => 'Cache marked for refresh on the next site visit.',
        ]);
    }
}
