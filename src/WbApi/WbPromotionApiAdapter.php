<?php

namespace App\WbApi;

use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pattern: Adapter — HTTP client for WB Promotion API.
 *
 * Money: internal + v1 write path use kopecks (bidMinorUnits).
 * Read path prefers bid_kopecks; plain `bid` on v0 get-bids is treated as rubles.
 */
final class WbPromotionApiAdapter implements WbPromotionApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly WbApiResponseMapper $mapper,
        private readonly WbApiMockProvider $mockProvider,
        #[Autowire('%env(bool:WB_API_MOCK)%')]
        private readonly bool $useMock,
        #[Autowire('%env(WB_API_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(WB_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire(service: 'limiter.wb_fullstats')]
        private readonly RateLimiterFactory $fullstatsLimiter,
        #[Autowire(service: 'limiter.wb_normquery_stats')]
        private readonly RateLimiterFactory $normqueryStatsLimiter,
        #[Autowire(service: 'limiter.wb_normquery_bids_read')]
        private readonly RateLimiterFactory $normqueryBidsReadLimiter,
        #[Autowire(service: 'limiter.wb_normquery_bids_write')]
        private readonly RateLimiterFactory $normqueryBidsWriteLimiter,
    ) {
    }

    public function getFullstats(array $advertIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $advertIds = array_values(array_unique(array_map('intval', $advertIds)));
        if ($advertIds === []) {
            return [];
        }
        if (\count($advertIds) > 50) {
            throw new \InvalidArgumentException('fullstats accepts at most 50 advertIds per request');
        }

        if ($this->useMock) {
            return $this->mapper->mapFullstats($this->mockProvider->getFullstats($advertIds));
        }

        $this->consume($this->fullstatsLimiter, 'fullstats');

        $response = $this->httpClient->request('GET', $this->baseUrl . '/adv/v3/fullstats', [
            'headers' => ['Authorization' => $this->apiKey],
            'query' => [
                'ids' => implode(',', $advertIds),
                'beginDate' => $from->format('Y-m-d'),
                'endDate' => $to->format('Y-m-d'),
            ],
        ]);

        $this->assertOk($response->getStatusCode(), 'WB fullstats request failed: ' . $response->getContent(false));

        return $this->mapper->mapFullstats($response->toArray());
    }

    public function getNormqueryStats(array $items, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($items === []) {
            return [];
        }

        if ($this->useMock) {
            return $this->mapper->mapNormqueryStats($this->mockProvider->getNormqueryStats($items));
        }

        $this->consume($this->normqueryStatsLimiter, 'normquery_stats');

        $response = $this->httpClient->request('POST', $this->baseUrl . '/adv/v0/normquery/stats', [
            'headers' => ['Authorization' => $this->apiKey],
            'json' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'items' => array_map(
                    static fn (array $i) => ['advertId' => $i['advertId'], 'nmId' => $i['nmId']],
                    $items,
                ),
            ],
        ]);

        $this->assertOk($response->getStatusCode(), 'WB normquery stats request failed: ' . $response->getContent(false));

        return $this->mapper->mapNormqueryStats($response->toArray());
    }

    public function getNormqueryBids(array $items): array
    {
        if ($items === []) {
            return [];
        }

        if ($this->useMock) {
            return $this->mapper->mapNormqueryBids($this->mockProvider->getNormqueryBids($items));
        }

        $this->consume($this->normqueryBidsReadLimiter, 'normquery_bids_read');

        $response = $this->httpClient->request('POST', $this->baseUrl . '/adv/v0/normquery/get-bids', [
            'headers' => ['Authorization' => $this->apiKey],
            'json' => [
                'items' => array_map(
                    static fn (array $i) => ['advert_id' => $i['advertId'], 'nm_id' => $i['nmId']],
                    $items,
                ),
            ],
        ]);

        $this->assertOk($response->getStatusCode(), 'WB normquery get-bids request failed: ' . $response->getContent(false));

        return $this->mapper->mapNormqueryBids($response->toArray());
    }

    public function setClusterBids(array $bids): void
    {
        if ($bids === []) {
            return;
        }
        if (\count($bids) > 100) {
            throw new \InvalidArgumentException('v1 normquery bids accepts at most 100 items');
        }

        if ($this->useMock) {
            return;
        }

        $this->consume($this->normqueryBidsWriteLimiter, 'normquery_bids_write');

        $response = $this->httpClient->request('POST', $this->baseUrl . '/api/advert/v1/normquery/bids', [
            'headers' => ['Authorization' => $this->apiKey],
            'json' => [
                'bids' => array_map(
                    static fn (array $b) => [
                        'advertId' => $b['advertId'],
                        'nmId' => $b['nmId'],
                        'normQuery' => $b['normQuery'],
                        'bidMinorUnits' => $b['bidKopecks'],
                    ],
                    $bids,
                ),
            ],
        ]);

        $this->assertOk($response->getStatusCode(), 'WB set bid failed: ' . $response->getContent(false));
    }

    public function deleteClusterBids(array $bids): void
    {
        if ($bids === []) {
            return;
        }

        if ($this->useMock) {
            return;
        }

        $this->consume($this->normqueryBidsWriteLimiter, 'normquery_bids_write');

        $response = $this->httpClient->request('DELETE', $this->baseUrl . '/adv/v0/normquery/bids', [
            'headers' => ['Authorization' => $this->apiKey],
            'json' => [
                'bids' => array_map(
                    static fn (array $b) => [
                        'advert_id' => $b['advertId'],
                        'nm_id' => $b['nmId'],
                        'norm_query' => $b['normQuery'],
                    ],
                    $bids,
                ),
            ],
        ]);

        $this->assertOk($response->getStatusCode(), 'WB delete cluster bid failed: ' . $response->getContent(false));
    }

    private function consume(RateLimiterFactory $factory, string $key): void
    {
        $limiter = $factory->create($key);
        $reservation = $limiter->reserve(1);
        $reservation->wait();
    }

    private function assertOk(int $status, string $message): void
    {
        if ($status === Response::HTTP_TOO_MANY_REQUESTS) {
            throw new \RuntimeException('WB rate limit exceeded (429): ' . $message);
        }
        if ($status !== Response::HTTP_OK) {
            throw new \RuntimeException($message);
        }
    }
}
