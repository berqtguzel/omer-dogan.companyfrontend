<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrettyHtmlSource
{
    private const RAW_BLOCK_PATTERN = '#<(script|style|pre|textarea)\b[^>]*>.*?</\1>#is';

    private const VOID_TAGS = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'param',
        'source',
        'track',
        'wbr',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->shouldFormat($request, $response)) {
            return $response;
        }

        $html = $response->getContent();
        if (!is_string($html) || stripos($html, '<!doctype html>') === false) {
            return $response;
        }

        $response->setContent($this->formatHtml($html));

        return $response;
    }

    private function shouldFormat(Request $request, Response $response): bool
    {
        if (!config('app.pretty_html_source', true)) {
            return false;
        }

        if ($request->header('X-Inertia') || $request->expectsJson()) {
            return false;
        }

        if ($response->getStatusCode() !== 200 || $response->headers->has('Content-Encoding')) {
            return false;
        }

        return str_contains($response->headers->get('Content-Type', ''), 'text/html');
    }

    private function formatHtml(string $html): string
    {
        $rawBlocks = [];
        $html = preg_replace_callback(
            self::RAW_BLOCK_PATTERN,
            function (array $matches) use (&$rawBlocks): string {
                $key = '___RAW_HTML_BLOCK_' . count($rawBlocks) . '___';
                $rawBlocks[$key] = $matches[0];

                return $key;
            },
            $html
        );

        if (!is_string($html)) {
            return '';
        }

        $parts = preg_split(
            '/(<[^>]+>)/',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($parts)) {
            return $this->restoreRawBlocks($html, $rawBlocks);
        }

        $lines = [];
        $depth = 0;

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, '</')) {
                $depth = max(0, $depth - 1);
                $lines[] = $this->indent($depth) . $part;
                continue;
            }

            $lines[] = $this->indent($depth) . $part;

            if ($this->isOpeningTag($part)) {
                $depth++;
            }
        }

        return $this->restoreRawBlocks(implode(PHP_EOL, $lines) . PHP_EOL, $rawBlocks);
    }

    private function isOpeningTag(string $part): bool
    {
        if (
            str_starts_with($part, '<!') ||
            str_starts_with($part, '<?') ||
            str_starts_with($part, '<!--') ||
            str_ends_with($part, '/>')
        ) {
            return false;
        }

        if (!preg_match('/^<([a-zA-Z0-9:-]+)/', $part, $matches)) {
            return false;
        }

        return !in_array(strtolower($matches[1]), self::VOID_TAGS, true);
    }

    private function restoreRawBlocks(string $html, array $rawBlocks): string
    {
        foreach ($rawBlocks as $key => $block) {
            $html = str_replace($key, trim($block), $html);
        }

        return $html;
    }

    private function indent(int $depth): string
    {
        return str_repeat('    ', $depth);
    }
}
