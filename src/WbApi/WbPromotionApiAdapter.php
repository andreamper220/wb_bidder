<?php

namespace App\WbApi;

use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pattern: Adapter — HTTP client for WB Promotion API.
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
    ) {
    }

    /** @return FullstatsCampaignDto[] */
    public function getFullstats(int $advertId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($this->useMock) {
            return $this->mapper->mapFullstats($this->mockProvider->getFullstats());
        }

        $response = $this->httpClient->request('GET', $this->baseUrl . '/adv/v3/fullstats', [
            'headers' => ['Authorization' => $this->apiKey],
            'query' => [
                'ids' => (string) $advertId,
                'beginDate' => $from->format('Y-m-d'),
                'endDate' => $to->format('Y-m-d'),
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException('WB fullstats request failed: ' . $response->getContent(false));
        }

        return $this->mapper->mapFullstats($response->toArray());
    }

    /** @return NormqueryClusterStatDto[] */
    public function getNormqueryStats(int $advertId, int $nmId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($this->useMock) {
            return $this->mapper->mapNormqueryStats($this->mockProvider->getNormqueryStats());
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . '/adv/v1/normquery/stats', [
            'headers' => ['Authorization' => $this->apiKey],
            'json' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'items' => [['advertId' => $advertId, 'nmId' => $nmId]],
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException('WB normquery stats request failed: ' . $response->getContent(false));
        }

        return $this->mapper->mapNormqueryStats($response->toArray());
    }

    /** @return NormqueryClusterBidDto[] */
    public function getNormqueryBids(int $advertId, int $nmId): array
    {
        if ($this->useMock) {
            return $this->mapper->mapNormqueryBids($this->mockProvider->getNormqueryBids());
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . '/adv/v0/normquery/get-bids', [
            'headers' => ['Authorization' => $this->apiKey],
            'json' => [
                'items' => [['advert_id' => $advertId, 'nm_id' => $nmId]],
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException('WB normquery get-bids request failed: ' . $response->getContent(false));
        }

        return $this->mapper->mapNormqueryBids($response->toArray());
    }

    public function setClusterBid(int $advertId, int $nmId, string $normQuery, int $bidKopecks): void
    {
        if ($this->useMock) {
            return;
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . '/adv/v0/normquery/bids', [
            'headers' => ['Authorization' => $this->apiKey],
            'json' => [
                'bids' => [[
                    'advert_id' => $advertId,
                    'nm_id' => $nmId,
                    'norm_query' => $normQuery,
                    'bid' => $bidKopecks,
                ]],
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException('WB set bid failed: ' . $response->getContent(false));
        }
    }
}
