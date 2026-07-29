<?php

namespace App\WbApi;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class WbApiMockProvider
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFullstats(): array
    {
        $path = $this->projectDir . '/tests/Fixtures/WbApi/fullstats.json';
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->shiftFullstatsDates($data);
    }

    public function getNormqueryStats(): array
    {
        $path = $this->projectDir . '/tests/Fixtures/WbApi/normquery_stats.json';
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $this->shiftNormqueryDates($data);
    }

    public function getNormqueryBids(): array
    {
        $path = $this->projectDir . '/tests/Fixtures/WbApi/normquery_bids.json';
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * Keeps demo fixtures inside the metrics window regardless of calendar date.
     *
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
