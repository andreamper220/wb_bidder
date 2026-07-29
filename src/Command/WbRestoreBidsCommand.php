<?php

namespace App\Command;

use App\Repository\CampaignRepository;
use App\Service\BidSnapshotRestoreService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wb:restore-bids', description: 'Restore cluster bids from the latest snapshot')]
final class WbRestoreBidsCommand extends Command
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly BidSnapshotRestoreService $restoreService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('campaign', 'c', InputOption::VALUE_REQUIRED, 'Campaign id', null)
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write to DB and WB (default is dry-run)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $campaignId = $input->getOption('campaign');
        if ($campaignId === null) {
            $io->error('Option --campaign is required');

            return Command::FAILURE;
        }

        $campaign = $this->campaignRepository->find((int) $campaignId);
        if ($campaign === null) {
            $io->error('Campaign not found');

            return Command::FAILURE;
        }

        $dryRun = !$input->getOption('apply');
        $snapshot = $this->restoreService->restoreLatest($campaign, $dryRun);
        if ($snapshot === null) {
            $io->warning('No snapshot found for this campaign');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Snapshot #%d from %s (%s)',
            $snapshot->getId(),
            $snapshot->getCreatedAt()->format('c'),
            $dryRun ? 'dry-run, nothing written' : 'applied to DB and WB',
        ));

        return Command::SUCCESS;
    }
}
