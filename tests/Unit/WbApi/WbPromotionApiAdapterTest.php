<?php

namespace App\Tests\Unit\WbApi;

use App\WbApi\WbApiMockProvider;
use App\WbApi\WbApiResponseMapper;
use App\WbApi\WbPromotionApiAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WbPromotionApiAdapterTest extends TestCase
{
    public function testGetNormqueryBidsInMockModeReturnsFixtureBids(): void
    {
        $adapter = $this->createAdapter(useMock: true);

        $bids = $adapter->getNormqueryBids(100001, 987654321);

        $this->assertNotEmpty($bids);
        $normQueries = array_map(static fn ($dto) => $dto->normQuery, $bids);
        $this->assertContains('кроссовки мужские', $normQueries);
        $this->assertContains('кроссовки черные', $normQueries);
        $this->assertContains('кроссовки бег', $normQueries);
    }

    public function testGetNormqueryBidsInLiveModeCallsWbApi(): void
    {
        $request = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$request) {
            $request = compact('method', 'url', 'options');

            return new MockResponse(json_encode([
                'bids' => [
                    [
                        'advert_id' => 1825035,
                        'nm_id' => 983512347,
                        'norm_query' => 'тестовая фраза',
                        'bid' => 1234,
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $adapter = new WbPromotionApiAdapter(
            $client,
            new WbApiResponseMapper(),
            new WbApiMockProvider(sys_get_temp_dir()),
            useMock: false,
            baseUrl: 'https://advert-api.wildberries.ru',
            apiKey: 'test-token',
        );

        $bids = $adapter->getNormqueryBids(1825035, 983512347);

        $this->assertNotNull($request);
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/adv/v0/normquery/get-bids', $request['url']);
        $payload = isset($request['options']['json'])
            ? $request['options']['json']
            : json_decode((string) ($request['options']['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            ['items' => [['advert_id' => 1825035, 'nm_id' => 983512347]]],
            $payload,
        );
        $this->assertCount(1, $bids);
        $this->assertSame('тестовая фраза', $bids[0]->normQuery);
        $this->assertSame(1234, $bids[0]->bidKopecks);
    }

    public function testGetNormqueryBidsInLiveModeThrowsOnNonOkResponse(): void
    {
        $client = new MockHttpClient(static fn () => new MockResponse('error', ['http_code' => 500]));
        $adapter = new WbPromotionApiAdapter(
            $client,
            new WbApiResponseMapper(),
            new WbApiMockProvider(sys_get_temp_dir()),
            useMock: false,
            baseUrl: 'https://advert-api.wildberries.ru',
            apiKey: 'test-token',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WB normquery get-bids request failed');

        $adapter->getNormqueryBids(1, 2);
    }

    private function createAdapter(bool $useMock): WbPromotionApiAdapter
    {
        return new WbPromotionApiAdapter(
            new MockHttpClient(),
            new WbApiResponseMapper(),
            new WbApiMockProvider(dirname(__DIR__, 3)),
            useMock: $useMock,
            baseUrl: 'https://advert-api.wildberries.ru',
            apiKey: '',
        );
    }
}
