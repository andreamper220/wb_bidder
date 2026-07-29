<?php

namespace App\Tests\Unit\WbApi;

use App\WbApi\WbApiMockProvider;
use PHPUnit\Framework\TestCase;

final class WbApiMockProviderTest extends TestCase
{
    public function testGetNormqueryBidsLoadsFixtureWithExpectedClusters(): void
    {
        $provider = new WbApiMockProvider(dirname(__DIR__, 3));
        $data = $provider->getNormqueryBids();

        $this->assertArrayHasKey('bids', $data);
        $this->assertCount(3, $data['bids']);

        $queries = array_column($data['bids'], 'norm_query');
        $this->assertSame(
            ['кроссовки мужские', 'кроссовки черные', 'кроссовки бег'],
            $queries,
        );
        $this->assertSame(5000, $data['bids'][0]['bid_kopecks']);
        $this->assertSame(8000, $data['bids'][1]['bid_kopecks']);
        $this->assertSame(12000, $data['bids'][2]['bid_kopecks']);
    }

    public function testGetNormqueryBidsEmptyForUnknownIds(): void
    {
        $provider = new WbApiMockProvider(dirname(__DIR__, 3));
        $data = $provider->getNormqueryBids([['advertId' => 1, 'nmId' => 2]]);

        $this->assertSame([], $data['bids']);
    }
}
