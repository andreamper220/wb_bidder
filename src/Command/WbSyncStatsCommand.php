<?php

namespace App\Command;

use App\Message\SyncCampaignStatsMessage;
use App\Repository\CampaignRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'wb:sync-stats', description: 'Dispatch sync jobs for all campaigns')]
final class WbSyncStatsCommand extends Command
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = 0;

        foreach ($this->campaignRepository->findAll() as $campaign) {
            $this->messageBus->dispatch(new SyncCampaignStatsMessage($campaign->getId() ?? 0));
            ++$count;
        }

        $io->success(sprintf('Dispatched sync for %d campaigns', $count));

        return Command::SUCCESS;
    }
}
