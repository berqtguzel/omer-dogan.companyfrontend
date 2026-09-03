<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ButtonTrackingController;
use App\Http\Controllers\WidgetController;
use App\Http\Controllers\OmrCacheWebhookController;

Route::post('/omr/cache-changed', OmrCacheWebhookController::class)
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/contact/forms/{id}/submit', [ContactFormController::class, 'submit'])
    ->middleware('throttle:contact-submit');

Route::get('/contact/forms', function (Request $request) {
    $locale = (string) $request->query('locale', session('locale', env('OMR_DEFAULT_LOCALE', 'de')));
    $forms = ContactFormController::getForms($locale);

    return response()->json([
        'data' => method_exists($forms, 'values') ? $forms->values()->all() : array_values((array) $forms),
    ]);
})->withoutMiddleware(['throttle:api']);

Route::get('/widgets', [WidgetController::class, 'index'])
    ->withoutMiddleware(['throttle:api']);

Route::post('/analytics/{endpoint}', [AnalyticsController::class, 'store']);
Route::post('/v2/analytics/{endpoint}', [AnalyticsController::class, 'store']);
Route::post('/button-tracking/track', [ButtonTrackingController::class, 'track']);
