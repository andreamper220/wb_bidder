<?php

namespace App\Command;

use App\Message\ApplyBidDecisionMessage;
use App\Repository\CampaignRepository;
use App\Bidding\Pipeline\BiddingPipeline;
use App\Enum\BidDecisionStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'wb:run-bidding', description: 'Run bidding pipeline for enabled campaigns')]
final class WbRunBiddingCommand extends Command
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly BiddingPipeline $biddingPipeline,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not apply bids');
        $this->addOption('campaign', 'c', InputOption::VALUE_REQUIRED, 'Campaign id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $campaignId = $input->getOption('campaign');

        $campaigns = $campaignId
            ? array_filter([$this->campaignRepository->find((int) $campaignId)])
            : $this->campaignRepository->findActiveForBidding();

        $total = 0;
        foreach ($campaigns as $campaign) {
            $decisions = $this->biddingPipeline->run($campaign, $dryRun);
            $total += count($decisions);
            $io->writeln(sprintf('Campaign %s: %d decisions', $campaign->getName(), count($decisions)));

            if (!$dryRun && !$campaign->isDryRun()) {
                foreach ($decisions as $decision) {
                    if ($decision->getStatus() === BidDecisionStatus::Pending && $decision->getId() !== null) {
                        $this->messageBus->dispatch(new ApplyBidDecisionMessage($decision->getId()));
                    }
                }
            }
        }

        $io->success(sprintf('Total decisions: %d (dry-run=%s)', $total, $dryRun ? 'yes' : 'no'));

        return Command::SUCCESS;
    }
}
