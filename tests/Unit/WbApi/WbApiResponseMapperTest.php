<?php

namespace App\Tests\Unit\WbApi;

use App\WbApi\WbApiResponseMapper;
use PHPUnit\Framework\TestCase;

final class WbApiResponseMapperTest extends TestCase
{
    private WbApiResponseMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new WbApiResponseMapper();
    }

    public function testMapNormqueryBidsTreatsPlainBidAsRubles(): void
    {
        $bids = $this->mapper->mapNormqueryBids([
            'bids' => [
                [
                    'advert_id' => 1825035,
                    'nm_id' => 983512347,
                    'norm_query' => 'кроссовки мужские',
                    'bid' => 70,
                ],
            ],
        ]);

        $this->assertCount(1, $bids);
        $this->assertSame(1825035, $bids[0]->advertId);
        $this->assertSame(983512347, $bids[0]->nmId);
        $this->assertSame('кроссовки мужские', $bids[0]->normQuery);
        // v0 `bid` = whole rubles → 70 ₽ = 7000 kopecks
        $this->assertSame(7000, $bids[0]->bidKopecks);
    }

    public function testMapNormqueryBidsSkipsEntriesWithoutNormQuery(): void
    {
        $bids = $this->mapper->mapNormqueryBids([
            'bids' => [
                ['advert_id' => 1, 'nm_id' => 2, 'bid' => 100],
                ['advert_id' => 1, 'nm_id' => 2, 'norm_query' => 'valid', 'bid' => 2],
            ],
        ]);

        $this->assertCount(1, $bids);
        $this->assertSame('valid', $bids[0]->normQuery);
        $this->assertSame(200, $bids[0]->bidKopecks);
    }

    public function testMapNormqueryBidsPrefersBidKopecks(): void
    {
        $bids = $this->mapper->mapNormqueryBids([
            'bids' => [
                [
                    'advertId' => 10,
                    'nmId' => 20,
                    'normQuery' => 'camel case',
                    'bid' => 3,
                    'bidKopecks' => 333,
                ],
            ],
        ]);

        $this->assertCount(1, $bids);
        $this->assertSame(10, $bids[0]->advertId);
        $this->assertSame(20, $bids[0]->nmId);
        $this->assertSame('camel case', $bids[0]->normQuery);
        $this->assertSame(333, $bids[0]->bidKopecks);
    }

    public function testMapNormqueryBidsReturnsEmptyArrayWhenKeyMissing(): void
    {
        $this->assertSame([], $this->mapper->mapNormqueryBids([]));
    }
}
