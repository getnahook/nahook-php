<?php

declare(strict_types=1);

namespace Nahook;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Nahook\Errors\NahookAPIError;
use Nahook\Errors\NahookNetworkError;
use Nahook\Errors\NahookTimeoutError;
use Psr\Http\Message\ResponseInterface;

final class HttpClient
{
    private const DEFAULT_BASE_URL = 'https://api.nahook.com';
    private const DEFAULT_TIMEOUT_MS = 30000;
    private const SDK_VERSION = '0.1.0';
    private const USER_AGENT = 'nahook-php/' . self::SDK_VERSION;
    private const BASE_DELAY_MS = 500;
    private const MAX_DELAY_MS = 10000;

    private const REGION_BASE_URLS = [
        'us' => 'https://us.api.nahook.com',
        'eu' => 'https://eu.api.nahook.com',
        'ap' => 'https://ap.api.nahook.com',
    ];

    private static function resolveBaseUrl(string $token): string
    {
        if (preg_match('/^nhk_([a-z]{2})_/', $token, $m)) {
            return self::REGION_BASE_URLS[$m[1]] ?? self::DEFAULT_BASE_URL;
        }
        return self::DEFAULT_BASE_URL;
    }

    private readonly string $token;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly int $retries;
    private readonly Client $client;

    /**
     * @param array{
     *     token: string,
     *     baseUrl?: string,
     *     timeout?: int,
     *     retries?: int,
     *     handler?: HandlerStack
     * } $config
     */
    public function __construct(array $config)
    {
        $this->token = $config['token'];
        $baseUrl = $config['baseUrl'] ?? null;
        $this->baseUrl = rtrim($baseUrl !== null && $baseUrl !== '' ? $baseUrl : self::resolveBaseUrl($config['token']), '/');
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT_MS;
        $this->retries = $config['retries'] ?? 0;

        $clientConfig = [
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout / 1000,
            'http_errors' => false,
        ];

        if (isset($config['handler'])) {
            $clientConfig['handler'] = $config['handler'];
        }

        $this->client = new Client($clientConfig);
    }

    /**
     * Make a request and return the decoded JSON body.
     *
     * @param array{
     *     method: string,
     *     path: string,
     *     body?: array<string, mixed>,
     *     query?: array<string, string|int|null>
     * } $opts
     * @return mixed
     */
    public function request(array $opts): mixed
    {
        $response = $this->executeWithRetry($opts);
        return $this->handleResponse($response);
    }

    /**
     * Make a request and return status code alongside decoded data.
     *
     * @param array{
     *     method: string,
     *     path: string,
     *     body?: array<string, mixed>,
     *     query?: array<string, string|int|null>
     * } $opts
     * @return array{status: int, data: mixed}
     */
    public function requestWithStatus(array $opts): array
    {
        $response = $this->executeWithRetry($opts);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            return ['status' => $statusCode, 'data' => $data];
        }

        throw $this->parseError($response);
    }

    private function executeWithRetry(array $opts): ResponseInterface
    {
        $url = $this->buildUrl($opts['path'], $opts['query'] ?? null);
        $hasBody = isset($opts['body']);

        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT,
        ];

        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }

        $requestOptions = ['headers' => $headers];
        if ($hasBody) {
            $requestOptions['body'] = json_encode($opts['body']);
        }

        /** @var \Throwable|null $lastError */
        $lastError = null;

        for ($attempt = 0; $attempt <= $this->retries; $attempt++) {
            if ($attempt > 0) {
                $retryAfterMs = ($lastError instanceof NahookAPIError && $lastError->retryAfter !== null)
                    ? $lastError->retryAfter * 1000
                    : null;
                $delay = $this->calculateDelay($attempt - 1, $retryAfterMs);
                usleep((int) ($delay * 1000));
            }

            try {
                $response = $this->client->request($opts['method'], $url, $requestOptions);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    return $response;
                }

                // Error response
                $error = $this->parseError($response);
                if ($attempt < $this->retries && $this->isRetryable($error)) {
                    $lastError = $error;
                    continue;
                }
                throw $error;
            } catch (ConnectException $e) {
                if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'Connection timed out')) {
                    $error = new NahookTimeoutError($this->timeout);
                } else {
                    $error = new NahookNetworkError($e);
                }

                if ($attempt < $this->retries && $this->isRetryable($error)) {
                    $lastError = $error;
                    continue;
                }
                throw $error;
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $error = $this->parseError($e->getResponse());
                    if ($attempt < $this->retries && $this->isRetryable($error)) {
                        $lastError = $error;
                        continue;
                    }
                    throw $error;
                }

                $error = new NahookNetworkError($e);
                if ($attempt < $this->retries && $this->isRetryable($error)) {
                    $lastError = $error;
                    continue;
                }
                throw $error;
            } catch (NahookAPIError | NahookNetworkError | NahookTimeoutError $e) {
                throw $e;
            } catch (\Throwable $e) {
                $error = new NahookNetworkError($e);
                if ($attempt < $this->retries && $this->isRetryable($error)) {
                    $lastError = $error;
                    continue;
                }
                throw $error;
            }
        }

        // Unreachable, but PHP needs it
        throw $lastError ?? new NahookNetworkError(new \RuntimeException('Unknown error'));
    }

    private function handleResponse(ResponseInterface $response): mixed
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            if ($statusCode === 204) {
                return null;
            }
            $body = (string) $response->getBody();
            return json_decode($body, true);
        }

        throw $this->parseError($response);
    }

    private function parseError(ResponseInterface $response): NahookAPIError
    {
        $retryAfterHeader = $response->getHeaderLine('retry-after');
        $retryAfterSecs = $retryAfterHeader !== '' ? (int) $retryAfterHeader : null;

        try {
            $body = json_decode((string) $response->getBody(), true);
            $code = $body['error']['code'] ?? 'unknown';
            $message = $body['error']['message'] ?? $response->getReasonPhrase();
        } catch (\Throwable) {
            $code = 'unknown';
            $message = $response->getReasonPhrase();
        }

        return new NahookAPIError($response->getStatusCode(), $code, $message, $retryAfterSecs);
    }

    /**
     * Build the full URL with query parameters.
     *
     * @param string $path
     * @param array<string, string|int|null>|null $query
     * @return string
     */
    private function buildUrl(string $path, ?array $query): string
    {
        $url = $this->baseUrl . $path;

        if ($query !== null) {
            $filtered = array_filter($query, fn($value) => $value !== null);
            if (!empty($filtered)) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        return $url;
    }

    /**
     * Calculate retry delay with exponential backoff and full jitter.
     */
    private function calculateDelay(int $attempt, ?int $retryAfterMs): float
    {
        if ($retryAfterMs !== null && $retryAfterMs > 0) {
            return (float) $retryAfterMs;
        }

        $exponential = min(self::MAX_DELAY_MS, self::BASE_DELAY_MS * pow(2, $attempt));
        return $exponential * (mt_rand() / mt_getrandmax());
    }

    /**
     * Whether an error is retryable.
     */
    private function isRetryable(\Throwable $error): bool
    {
        if ($error instanceof NahookAPIError) {
            return $error->isRetryable();
        }
        return $error instanceof NahookNetworkError || $error instanceof NahookTimeoutError;
    }
}
