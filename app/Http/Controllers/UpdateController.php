<?php

namespace App\Http\Controllers;

use App\Services\UpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller
{
    public function check(UpdateService $update): JsonResponse
    {
        if (! $this->authorized($update, request())) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        $current = $update->getCurrentVersion();
        $latest = $update->checkLatestVersion();

        return response()->json([
            'ok' => true,
            'current' => $current,
            'latest' => $latest,
            'update_available' => $update->compareVersions(
                $current['version'] ?? null,
                $latest['version'] ?? null
            ),
        ]);
    }

    public function run(Request $request, UpdateService $update): JsonResponse
    {
        if (! $update->enabled()) {
            return response()->json(['ok' => false, 'message' => 'Update disabled'], 404);
        }

        if (! $this->authorized($update, $request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($update->isRunning()) {
            return response()->json([
                'ok' => false,
                'message' => 'Update already running',
                'status' => $update->status(),
            ], 409);
        }

        $version = (string) $request->input('version', 'latest');
        $update->dispatchBackground($version);

        return response()->json([
            'ok' => true,
            'message' => 'Update queued',
            'version' => $version,
            'status' => $update->status(),
        ], 202);
    }

    public function webhook(Request $request, UpdateService $update): JsonResponse
    {
        if (! $update->enabled()) {
            return response()->json(['ok' => false, 'message' => 'Update disabled'], 404);
        }

        if (! $this->authorized($update, $request)) {
            Log::warning('Update webhook unauthorized', ['ip' => $request->ip()]);

            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        $event = $request->header('X-GitHub-Event');
        if ($event === 'ping') {
            return response()->json(['ok' => true, 'message' => 'pong']);
        }

        if ($event && ! in_array($event, ['push', 'workflow_dispatch', 'release'], true)) {
            return response()->json(['ok' => true, 'message' => 'Event ignored']);
        }

        if ($update->isRunning()) {
            return response()->json(['ok' => false, 'message' => 'Update already running'], 409);
        }

        $update->dispatchBackground('latest');

        return response()->json(['ok' => true, 'message' => 'Update queued'], 202);
    }

    public function status(UpdateService $update): JsonResponse
    {
        if (! $this->authorized($update, request())) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'ok' => true,
            'running' => $update->isRunning(),
            'status' => $update->status(),
            'current' => $update->getCurrentVersion(),
        ]);
    }

    private function authorized(UpdateService $update, Request $request): bool
    {
        if (! $update->enabled()) {
            return false;
        }

        $allowed = config('update.allowed_ips', []);
        if ($allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            return false;
        }

        $githubSecret = (string) config('update.github_webhook_secret');
        if ($githubSecret !== '') {
            $signature = (string) $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $githubSecret);
            if ($signature !== '' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        $secret = (string) config('update.secret');
        if ($secret === '') {
            return false;
        }

        $token = (string) ($request->header('X-Update-Token') ?? $request->query('token', ''));

        return $token !== '' && hash_equals($secret, $token);
    }
}
