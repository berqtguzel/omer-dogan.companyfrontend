<?php

namespace App\Http\Controllers;

use App\Services\MediaMirrorService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MediaCacheController extends Controller
{
    public function proxy(Request $request, string $token, MediaMirrorService $mirror): Response|RedirectResponse
    {
        $sourceUrl = $mirror->proxySourceUrl($token, $request->query());

        abort_unless($sourceUrl, 404);

        if (! config('media_mirror.download_on_request', false)) {
            return redirect()->away($sourceUrl, 302, [
                'Cache-Control' => 'no-store',
            ]);
        }

        $localUrl = $mirror->syncUrl($sourceUrl);
        $localPath = is_string($localUrl) ? parse_url($localUrl, PHP_URL_PATH) : null;

        if (is_string($localPath) && str_starts_with($localPath, '/media-cache/')) {
            return $this->show(substr($localPath, strlen('/media-cache/')));
        }

        if (is_string($localPath) && str_starts_with($localPath, '/storage/media-cache/')) {
            return $this->show(substr($localPath, strlen('/storage/media-cache/')));
        }

        return response('Remote media could not be retrieved.', 502, [
            'Cache-Control' => 'no-store',
        ]);
    }

    public function show(string $path): Response
    {
        $path = str_replace('\\', '/', trim($path, '/'));

        abort_if($path === '' || str_contains($path, '..'), 404);

        $cacheDir = trim((string) config('media_mirror.cache_dir', 'media-cache'), '/');
        $relativePath = $cacheDir.'/'.ltrim($path, '/');
        $disk = Storage::disk((string) config('media_mirror.disk', 'public'));

        abort_unless($disk->exists($relativePath), 404);

        return response($disk->get($relativePath), 200, [
            'Content-Type' => $disk->mimeType($relativePath) ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
