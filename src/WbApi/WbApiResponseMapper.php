<?php

namespace App\WbApi;

use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\FullstatsDayDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;

/**
 * Pattern: Adapter — maps WB API JSON to DTOs.
 */
final class WbApiResponseMapper
{
    /** @return FullstatsCampaignDto[] */
    public function mapFullstats(array $data): array
    {
        $result = [];

        foreach ($data as $item) {
            $days = [];
            foreach ($item['days'] ?? [] as $day) {
                $days[] = new FullstatsDayDto(
                    new \DateTimeImmutable($day['date']),
                    (int) ($day['views'] ?? 0),
                    (int) ($day['clicks'] ?? 0),
                    (int) ($day['orders'] ?? 0),
                    (string) ($day['sum'] ?? 0),
                    (string) ($day['sum_price'] ?? 0),
                );
            }

            $result[] = new FullstatsCampaignDto(
                (int) $item['advertId'],
                (int) ($item['views'] ?? 0),
                (int) ($item['clicks'] ?? 0),
                (int) ($item['orders'] ?? 0),
                (string) ($item['sum'] ?? 0),
                (string) ($item['sum_price'] ?? 0),
                $days,
            );
        }

        return $result;
    }

    /** @return NormqueryClusterStatDto[] */
    public function mapNormqueryStats(array $data): array
    {
        $result = [];

        foreach ($data['items'] ?? [] as $item) {
            $advertId = (int) ($item['advertId'] ?? $item['advert_id'] ?? 0);
            $nmId = (int) ($item['nmId'] ?? $item['nm_id'] ?? 0);

            foreach ($item['dailyStats'] ?? [] as $row) {
                $stat = $row['stat'] ?? $row;
                $normQuery = (string) ($stat['normQuery'] ?? $stat['norm_query'] ?? '');
                $date = new \DateTimeImmutable($row['date'] ?? $stat['date'] ?? 'now');

                $result[] = new NormqueryClusterStatDto(
                    $advertId,
                    $nmId,
                    $normQuery,
                    $date,
                    (int) ($stat['views'] ?? 0),
                    (int) ($stat['clicks'] ?? 0),
                    (int) ($stat['orders'] ?? 0),
                    (string) ($stat['spend'] ?? $stat['sum'] ?? 0),
                );
            }
        }

        return $result;
    }

    /** @return NormqueryClusterBidDto[] */
    public function mapNormqueryBids(array $data): array
    {
        $result = [];

        foreach ($data['bids'] ?? [] as $item) {
            $normQuery = (string) ($item['normQuery'] ?? $item['norm_query'] ?? '');
            if ($normQuery === '') {
                continue;
            }

            $result[] = new NormqueryClusterBidDto(
                (int) ($item['advertId'] ?? $item['advert_id'] ?? 0),
                (int) ($item['nmId'] ?? $item['nm_id'] ?? 0),
                $normQuery,
                (int) ($item['bid'] ?? $item['bidKopecks'] ?? 0),
            );
        }

        return $result;
    }
}
