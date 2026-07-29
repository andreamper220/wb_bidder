<?php

namespace App\Admin;

use App\Demo\DemoStandService;
use App\Repository\CampaignRepository;

final class ProductionReadinessService
{
    private const DEFAULT_SECRETS = [
        'change_me_in_prod',
        'docker_secret_change_me',
    ];

    public function __construct(
        private readonly CampaignRepository $campaignRepository,
    ) {
    }

    /**
     * @return array{
     *     checks: list<array{id: string, label: string, description: string, ok: bool, critical: bool, hint?: string}>,
     *     ready: bool,
     *     mockApi: bool,
     *     apiKeyConfigured: bool,
     *     apiKeyMasked: ?string,
     *     realCampaignsCount: int,
     *     liveBiddingCount: int
     * }
     */
    public function build(): array
    {
        $mockApi = filter_var($_ENV['WB_API_MOCK'] ?? '1', FILTER_VALIDATE_BOOL);
        $apiKey = trim((string) ($_ENV['WB_API_KEY'] ?? ''));
        $appSecret = (string) ($_ENV['APP_SECRET'] ?? '');

        $campaigns = $this->campaignRepository->findAll();
        $realCampaigns = array_filter(
            $campaigns,
            static fn ($c) => $c->getWbAdvertId() !== DemoStandService::DEMO_WB_ADVERT_ID,
        );
        $liveBidding = array_filter(
            $realCampaigns,
            static fn ($c) => $c->isBiddingEnabled() && !$c->isDryRun() && $c->isActive(),
        );

        $checks = [
            [
                'id' => 'api_mock_off',
                'label' => 'WB_API_MOCK=0',
                'description' => 'Отключён mock-режим — запросы идут в реальный API Wildberries.',
                'ok' => !$mockApi,
                'critical' => true,
                'hint' => 'В .env.local или docker-compose: WB_API_MOCK=0',
            ],
            [
                'id' => 'api_key',
                'label' => 'WB_API_KEY задан',
                'description' => 'Токен API продвижения WB (кабинет → Настройки → Доступ к API).',
                'ok' => $apiKey !== '',
                'critical' => true,
                'hint' => 'WB_API_KEY=<ваш токен> в .env.local (не коммитить!)',
            ],
            [
                'id' => 'app_secret',
                'label' => 'APP_SECRET изменён',
                'description' => 'Секрет Symfony не равен значению по умолчанию из репозитория.',
                'ok' => $appSecret !== '' && !in_array($appSecret, self::DEFAULT_SECRETS, true),
                'critical' => true,
                'hint' => 'Сгенерируйте случайную строку: openssl rand -hex 32',
            ],
            [
                'id' => 'real_campaign',
                'label' => 'Реальная кампания добавлена',
                'description' => 'В системе есть кампания с настоящим WB advert ID (не демо #100001).',
                'ok' => count($realCampaigns) > 0,
                'critical' => false,
                'hint' => 'Кампании → + Новая кампания → укажите advertId из кабинета WB',
            ],
            [
                'id' => 'sync_done',
                'label' => 'Статистика синхронизирована',
                'description' => 'Хотя бы у одной реальной кампании есть кластеры (создаются при wb:sync-stats).',
                'ok' => $this->hasSyncedRealCampaign($realCampaigns),
                'critical' => false,
                'hint' => 'docker compose exec app php bin/console wb:sync-stats',
            ],
            [
                'id' => 'dry_run_tested',
                'label' => 'Dry-run проверен',
                'description' => 'Решения по ставкам рассчитаны в dry-run перед боевым запуском.',
                'ok' => $this->hasDryRunCampaign($realCampaigns),
                'critical' => false,
                'hint' => 'Включите dry-run, запустите wb:run-bidding --dry-run, проверьте Историю ставок',
            ],
            [
                'id' => 'live_bidding',
                'label' => 'Боевой автобиддинг включён',
                'description' => 'Кампания: active + автобиддинг ON + dry-run OFF — ставки уходят в WB.',
                'ok' => count($liveBidding) > 0,
                'critical' => false,
                'hint' => 'Только после проверки решений в dry-run снимите флаг dry-run',
            ],
        ];

        $infraReady = array_reduce(
            $checks,
            static fn (bool $carry, array $check) => $carry && (!$check['critical'] || $check['ok']),
            true,
        );

        return [
            'checks' => $checks,
            'infraReady' => $infraReady,
            'ready' => $infraReady && count($liveBidding) > 0,
            'mockApi' => $mockApi,
            'apiKeyConfigured' => $apiKey !== '',
            'apiKeyMasked' => $apiKey !== '' ? $this->maskApiKey($apiKey) : null,
            'realCampaignsCount' => count($realCampaigns),
            'liveBiddingCount' => count($liveBidding),
        ];
    }

  /**
   * @param list<\App\Entity\Campaign> $campaigns
   */
    private function hasSyncedRealCampaign(array $campaigns): bool
    {
        foreach ($campaigns as $campaign) {
            if ($campaign->getClusters()->count() > 0) {
                return true;
            }
        }

        return false;
    }

  /**
   * @param list<\App\Entity\Campaign> $campaigns
   */
    private function hasDryRunCampaign(array $campaigns): bool
    {
        foreach ($campaigns as $campaign) {
            if ($campaign->isBiddingEnabled() && $campaign->isDryRun()) {
                return true;
            }
        }

        return false;
    }

    private function maskApiKey(string $apiKey): string
    {
        $len = strlen($apiKey);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($apiKey, 0, 4) . str_repeat('•', min(12, $len - 8)) . substr($apiKey, -4);
    }
}
