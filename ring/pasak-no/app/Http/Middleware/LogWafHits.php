<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pencatat serangan masuk untuk demo WAF (tanpa Pasak).
 * Menyimpan request mencurigakan ke storage/logs/waf-hits.log (JSON lines).
 */
class LogWafHits
{
    /** @var string[] */
    private const PATTERNS = [
        '\.\./', '%2e%2e', '%00', '<script', 'union\s+select',
        "'\s*or\s+'1'\s*=\s*'1", '{{7\*7}}', '\.env', '\.git',
        'wp-admin', 'php\.jpg', '\.php', 'etc/passwd', 'select\s+\*',
        'sleep\(', 'benchmark\(', 'nikto', 'sqlmap', 'masscan', 'nmap',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        $this->logIfSuspicious($request, $response);

        return $response;
    }

    private function logIfSuspicious(Request $request, mixed $response): void
    {
        $haystack = strtolower(
            urldecode($request->decodedPath().' '.$request->getQueryString().' '.$request->getContent()).' '.$request->userAgent().' '.$request->fullUrl()
        );
        $matched = null;
        foreach (self::PATTERNS as $pattern) {
            if (@preg_match('#'.$pattern.'#i', $haystack) === 1) {
                $matched = $pattern;
                break;
            }
        }
        if ($matched === null) {
            return;
        }
        $status = $response instanceof Response ? $response->getStatusCode() : 200;
        $entry = [
            'time' => now()->toDateTimeString(),
            'ip' => $request->ip(),
            'virtual_ip' => $request->header('CF-Connecting-IP') ?? $request->header('X-Forwarded-For'),
            'method' => $request->method(),
            'path' => '/'.$request->decodedPath().($request->getQueryString() ? '?'.$request->getQueryString() : ''),
            'ua' => mb_substr((string) $request->userAgent(), 0, 120),
            'pattern' => $matched,
            'status' => $status,
            'blocked' => in_array($status, [403, 429], true),
        ];
        @file_put_contents(
            storage_path('logs/waf-hits.log'),
            json_encode($entry, JSON_UNESCAPED_SLASHES).PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
