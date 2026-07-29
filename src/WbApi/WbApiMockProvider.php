<?php

namespace App\WbApi;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WbApiMockProvider
{
    public const DEMO_ADVERT_ID = 100001;
    public const DEMO_NM_ID = 987654321;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param list<int> $advertIds
     */
    public function getFullstats(array $advertIds = []): array
    {
        if ($advertIds !== [] && !\in_array(self::DEMO_ADVERT_ID, $advertIds, true)) {
            return [];
        }

        $path = $this->projectDir . '/tests/Fixtures/WbApi/fullstats.json';
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->shiftFullstatsDates($data);
    }

    /**
     * @param list<array{advertId: int, nmId: int}> $items
     */
    public function getNormqueryStats(array $items = []): array
    {
        if ($items !== [] && !$this->itemsMatchDemo($items)) {
            return ['items' => []];
        }

        $path = $this->projectDir . '/tests/Fixtures/WbApi/normquery_stats.json';
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->shiftNormqueryDates($data);
    }

    /**
     * @param list<array{advertId: int, nmId: int}> $items
     */
    public function getNormqueryBids(array $items = []): array
    {
        if ($items !== [] && !$this->itemsMatchDemo($items)) {
            return ['bids' => []];
        }

        $path = $this->projectDir . '/tests/Fixtures/WbApi/normquery_bids.json';

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{advertId: int, nmId: int}> $items
     */
    private function itemsMatchDemo(array $items): bool
    {
        foreach ($items as $item) {
            if ((int) $item['advertId'] === self::DEMO_ADVERT_ID && (int) $item['nmId'] === self::DEMO_NM_ID) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     *
     * @return array<int, array<string, mixed>>
     */
    private function shiftFullstatsDates(array $data): array
    {
        $today = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');

        foreach ($data as &$campaign) {
            if (!isset($campaign['days']) || !is_array($campaign['days'])) {
                continue;
            }

            $targets = [$yesterday, $today];
            foreach ($campaign['days'] as $i => &$day) {
                $target = $targets[$i] ?? $today;
                $day['date'] = $target->format('Y-m-d\TH:i:s\Z');
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function shiftNormqueryDates(array $data): array
    {
        $today = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');
        $targets = [$yesterday, $today];

        foreach ($data['items'] ?? [] as &$item) {
            if (!isset($item['dailyStats']) || !is_array($item['dailyStats'])) {
                continue;
            }

            $dayIndex = 0;
            foreach ($item['dailyStats'] as &$daily) {
                $target = $targets[$dayIndex % 2];
                $daily['date'] = $target->format('Y-m-d');
                ++$dayIndex;
            }
        }

        return $data;
    }
}
