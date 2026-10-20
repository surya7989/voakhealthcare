<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Tests\TestFramework as T;

/**
 * Voak Health Care — Phase 4 HTTP Smoke Test Suite.
 * Launches a real local PHP built-in web server and executes live HTTP requests.
 */
class HttpSmokeTest
{
    public static function run(): void
    {
        $backendRoot = dirname(__DIR__, 2);
        $host = '127.0.0.1';
        $port = 8199;
        $publicDir = $backendRoot . '/public';

        echo "\n=== SUITE: Real HTTP Smoke Tests on Local Web Server (http://{$host}:{$port}) ===\n";

        // Launch PHP server in background process
        $cmd = sprintf('php -S %s:%d -t "%s"', $host, $port, $publicDir);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $backendRoot);
        if (!is_resource($process)) {
            echo "FATAL: Could not start PHP built-in server.\n";
            return;
        }

        // Wait for server to bind
        usleep(600000); // 600ms

        $httpGet = function (string $url, array $headers = []): array {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'ignore_errors' => true,
                    'timeout' => 5,
                ],
            ];
            $ctx = stream_context_create($opts);
            $body = @file_get_contents($url, false, $ctx);
            $responseHeaders = $http_response_header ?? [];

            $statusCode = 0;
            if (!empty($responseHeaders) && preg_match('/HTTP\/[0-9.]+\s+([0-9]{3})/', $responseHeaders[0], $m)) {
                $statusCode = (int)$m[1];
            }

            $parsedHeaders = [];
            foreach ($responseHeaders as $h) {
                if (str_contains($h, ':')) {
                    [$k, $v] = explode(':', $h, 2);
                    $parsedHeaders[strtolower(trim($k))] = trim($v);
                }
            }

            return [
                'status' => $statusCode,
                'body' => $body ?: '',
                'headers' => $parsedHeaders,
            ];
        };

        $httpPost = function (string $url, string $body, array $headers = []): array {
            $headers[] = 'Content-Length: ' . strlen($body);
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $body,
                    'ignore_errors' => true,
                    'timeout' => 5,
                ],
            ];
            $ctx = stream_context_create($opts);
            $resBody = @file_get_contents($url, false, $ctx);
            $responseHeaders = $http_response_header ?? [];

            $statusCode = 0;
            if (!empty($responseHeaders) && preg_match('/HTTP\/[0-9.]+\s+([0-9]{3})/', $responseHeaders[0], $m)) {
                $statusCode = (int)$m[1];
            }

            $parsedHeaders = [];
            foreach ($responseHeaders as $h) {
                if (str_contains($h, ':')) {
                    [$k, $v] = explode(':', $h, 2);
                    $parsedHeaders[strtolower(trim($k))] = trim($v);
                }
            }

            return [
                'status' => $statusCode,
                'body' => $resBody ?: '',
                'headers' => $parsedHeaders,
            ];
        };

        try {
            $baseUrl = "http://{$host}:{$port}";

            T::test('HTTP Smoke: GET /api/health returns 200 and {"status": "ok"}', function () use ($baseUrl, $httpGet) {
                $res = $httpGet("{$baseUrl}/api/health");
                T::assertEquals(200, $res['status'], "Status should be 200");
                $json = json_decode($res['body'], true);
                T::assertEquals('ok', $json['status']);
                T::assertContains('application/json', $res['headers']['content-type'] ?? '');
                T::assertEquals('nosniff', $res['headers']['x-content-type-options'] ?? '');
                T::assertEquals('DENY', $res['headers']['x-frame-options'] ?? '');
            });

            T::test('HTTP Smoke: GET /api/v1/health returns 200 with Phase 2 envelope', function () use ($baseUrl, $httpGet) {
                $res = $httpGet("{$baseUrl}/api/v1/health");
                T::assertEquals(200, $res['status']);
                $json = json_decode($res['body'], true);
                T::assertTrue($json['success']);
                T::assertEquals('healthy', $json['data']['status']);
                T::assertArrayHasKey('correlation_id', $json['meta']);
            });

            T::test('HTTP Smoke: GET /api/v1/public/partners-preview returns 200 with OPEN-03 note', function () use ($baseUrl, $httpGet) {
                $res = $httpGet("{$baseUrl}/api/v1/public/partners-preview");
                T::assertEquals(200, $res['status']);
                $json = json_decode($res['body'], true);
                T::assertTrue($json['success']);
                T::assertEquals('PAGE-PUB-10', $json['data']['page_id']);
            });

            T::test('HTTP Smoke: GET /api/v1/nonexistent returns authentic 404 Not Found', function () use ($baseUrl, $httpGet) {
                $res = $httpGet("{$baseUrl}/api/v1/nonexistent");
                T::assertEquals(404, $res['status']);
                $json = json_decode($res['body'], true);
                T::assertFalse($json['success']);
                T::assertEquals('NOT_FOUND', $json['error']['code']);
            });

            T::test('HTTP Smoke: POST /api/health returns 405 Method Not Allowed with Allow header', function () use ($baseUrl, $httpPost) {
                $res = $httpPost("{$baseUrl}/api/health", '{}', ['Content-Type: application/json']);
                T::assertEquals(405, $res['status']);
                T::assertArrayHasKey('allow', $res['headers']);
                T::assertContains('GET', $res['headers']['allow']);
            });

            T::test('HTTP Smoke: POST with malformed JSON returns 400 Bad Request', function () use ($baseUrl, $httpPost) {
                $res = $httpPost("{$baseUrl}/api/health", '{malformed_json_here', ['Content-Type: application/json']);
                T::assertEquals(400, $res['status']);
                $json = json_decode($res['body'], true);
                T::assertFalse($json['success']);
                T::assertEquals('BAD_REQUEST', $json['error']['code']);
            });
        } finally {
            // Terminate server process cleanly
            $pStatus = @proc_get_status($process);
            if (is_array($pStatus) && !empty($pStatus['running'])) {
                if (stripos(PHP_OS, 'WIN') === 0) {
                    @exec("taskkill /F /T /PID " . $pStatus['pid'] . " 2>&1");
                } else {
                    @proc_terminate($process);
                }
            }
            @fclose($pipes[0]);
            @fclose($pipes[1]);
            @fclose($pipes[2]);
            @proc_close($process);
        }
    }
}
