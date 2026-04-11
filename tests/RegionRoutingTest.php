<?php

declare(strict_types=1);

namespace Nahook\Tests;

use Nahook\HttpClient;
use PHPUnit\Framework\TestCase;

class RegionRoutingTest extends TestCase
{
    private \ReflectionMethod $resolveBaseUrl;
    private \ReflectionMethod $calculateDelay;

    protected function setUp(): void
    {
        $this->resolveBaseUrl = new \ReflectionMethod(HttpClient::class, 'resolveBaseUrl');
        $this->resolveBaseUrl->setAccessible(true);

        $this->calculateDelay = new \ReflectionMethod(HttpClient::class, 'calculateDelay');
        $this->calculateDelay->setAccessible(true);
    }

    // ── Region Routing ──

    public function testResolveBaseUrlUsRegion(): void
    {
        $url = $this->resolveBaseUrl->invoke(null, 'nhk_us_abc123');
        $this->assertSame('https://us.api.nahook.com', $url);
    }

    public function testResolveBaseUrlEuRegion(): void
    {
        $url = $this->resolveBaseUrl->invoke(null, 'nhk_eu_abc123');
        $this->assertSame('https://eu.api.nahook.com', $url);
    }

    public function testResolveBaseUrlApRegion(): void
    {
        $url = $this->resolveBaseUrl->invoke(null, 'nhk_ap_abc123');
        $this->assertSame('https://ap.api.nahook.com', $url);
    }

    public function testResolveBaseUrlFallsBackForUnknownRegion(): void
    {
        $url = $this->resolveBaseUrl->invoke(null, 'nhk_zz_abc123');
        $this->assertSame('https://api.nahook.com', $url);
    }

    public function testBaseUrlOptionOverridesRegionResolution(): void
    {
        // When baseUrl is explicitly provided, it should be used regardless of token region.
        // We test this via the constructor — the baseUrl property on the instance should reflect the override.
        $baseUrlProp = new \ReflectionProperty(HttpClient::class, 'baseUrl');
        $baseUrlProp->setAccessible(true);

        $client = new HttpClient([
            'token' => 'nhk_eu_abc123',
            'baseUrl' => 'https://custom.example.com',
        ]);

        $this->assertSame('https://custom.example.com', $baseUrlProp->getValue($client));
    }

    // ── Retry Delay ──

    public function testCalculateDelayReturnsBetweenZeroAndExponentialCap(): void
    {
        $client = new HttpClient([
            'token' => 'nhk_us_abc123',
            'baseUrl' => 'https://api.test.com',
        ]);

        // attempt 0: cap = min(10000, 500 * 2^0) = 500
        // jitter produces value in [0, 500)
        for ($i = 0; $i < 20; $i++) {
            $delay = $this->calculateDelay->invoke($client, 0, null);
            $this->assertGreaterThanOrEqual(0, $delay);
            $this->assertLessThanOrEqual(500, $delay);
        }
    }

    public function testCalculateDelayCapsAtMaxDelay(): void
    {
        $client = new HttpClient([
            'token' => 'nhk_us_abc123',
            'baseUrl' => 'https://api.test.com',
        ]);

        // attempt 10: 500 * 2^10 = 512000, capped to MAX_DELAY_MS (10000)
        for ($i = 0; $i < 20; $i++) {
            $delay = $this->calculateDelay->invoke($client, 10, null);
            $this->assertGreaterThanOrEqual(0, $delay);
            $this->assertLessThanOrEqual(10000, $delay);
        }
    }

    public function testCalculateDelayUsesRetryAfterWhenProvided(): void
    {
        $client = new HttpClient([
            'token' => 'nhk_us_abc123',
            'baseUrl' => 'https://api.test.com',
        ]);

        $delay = $this->calculateDelay->invoke($client, 0, 5000);
        $this->assertSame(5000.0, $delay);
    }
}
