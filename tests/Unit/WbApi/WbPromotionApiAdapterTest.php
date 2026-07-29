<?php

namespace App\Tests\Unit\WbApi;

use App\WbApi\WbApiMockProvider;
use App\WbApi\WbApiResponseMapper;
use App\WbApi\WbPromotionApiAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class WbPromotionApiAdapterTest extends TestCase
{
    public function testGetNormqueryBidsInMockModeReturnsFixtureBids(): void
    {
        $adapter = $this->createAdapter(useMock: true);

        $bids = $adapter->getNormqueryBids([
            ['advertId' => 100001, 'nmId' => 987654321],
        ]);

        $this->assertNotEmpty($bids);
        $this->assertSame(5000, $bids[0]->bidKopecks);
    }

    public function testMockReturnsEmptyForUnknownCampaign(): void
    {
        $adapter = $this->createAdapter(useMock: true);

        $bids = $adapter->getNormqueryBids([
            ['advertId' => 999, 'nmId' => 1],
        ]);

        $this->assertSame([], $bids);
    }

    public function testSetClusterBidsSendsKopecksViaV1(): void
    {
        $request = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$request) {
            $request = compact('method', 'url', 'options');

            return new MockResponse('{"success":[],"failed":[]}');
        });

        $adapter = $this->createAdapter(useMock: false, client: $client);
        $adapter->setClusterBids([[
            'advertId' => 1825035,
            'nmId' => 983512347,
            'normQuery' => 'фраза',
            'bidKopecks' => 15000,
        ]]);

        $this->assertNotNull($request);
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/api/advert/v1/normquery/bids', $request['url']);
        $payload = $request['options']['json'] ?? json_decode((string) ($request['options']['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'bids' => [[
                'advertId' => 1825035,
                'nmId' => 983512347,
                'normQuery' => 'фраза',
                'bidMinorUnits' => 15000,
            ]],
        ], $payload);
    }

    public function testGetFullstatsBatchesIds(): void
    {
        $request = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$request) {
            $request = compact('method', 'url', 'options');

            return new MockResponse('[]');
        });

        $adapter = $this->createAdapter(useMock: false, client: $client);
        $adapter->getFullstats([1, 2, 3], new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-07'));

        $this->assertSame('1,2,3', $request['options']['query']['ids']);
    }

    public function testGetNormqueryStatsUsesV0Path(): void
    {
        $request = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$request) {
            $request = compact('method', 'url', 'options');

            return new MockResponse(json_encode(['items' => []], JSON_THROW_ON_ERROR));
        });

        $adapter = $this->createAdapter(useMock: false, client: $client);
        $adapter->getNormqueryStats(
            [['advertId' => 1, 'nmId' => 2]],
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-07'),
        );

        $this->assertStringEndsWith('/adv/v0/normquery/stats', $request['url']);
    }

    public function testDeleteClusterBidsCallsDeleteEndpoint(): void
    {
        $request = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$request) {
            $request = compact('method', 'url', 'options');

            return new MockResponse('{"status":"ok"}');
        });

        $adapter = $this->createAdapter(useMock: false, client: $client);
        $adapter->deleteClusterBids([['advertId' => 1, 'nmId' => 2, 'normQuery' => 'фраза']]);

        $this->assertSame('DELETE', $request['method']);
        $this->assertStringEndsWith('/adv/v0/normquery/bids', $request['url']);
    }

    private function createAdapter(bool $useMock, ?MockHttpClient $client = null): WbPromotionApiAdapter
    {
        $storage = new InMemoryStorage();
        $config = [
            'id' => 'test',
            'policy' => 'token_bucket',
            'limit' => 1000,
            'rate' => ['interval' => '1 second', 'amount' => 1000],
        ];

        return new WbPromotionApiAdapter(
            $client ?? new MockHttpClient(),
            new WbApiResponseMapper(),
            new WbApiMockProvider(dirname(__DIR__, 3)),
            useMock: $useMock,
            baseUrl: 'https://advert-api.wildberries.ru',
            apiKey: 'test-token',
            fullstatsLimiter: new RateLimiterFactory($config, $storage),
            normqueryStatsLimiter: new RateLimiterFactory($config, $storage),
            normqueryBidsReadLimiter: new RateLimiterFactory($config, $storage),
            normqueryBidsWriteLimiter: new RateLimiterFactory($config, $storage),
        );
    }
}
