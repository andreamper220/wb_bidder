<?php

namespace App\Command;

use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Repository\CampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'wb:seed-demo', description: 'Seed demo campaign for mock WB data')]
final class WbSeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CampaignRepository $campaignRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existing = $this->campaignRepository->findOneBy(['wbAdvertId' => 100001]);
        if ($existing !== null) {
            $io->success('Demo campaign already exists (id=' . $existing->getId() . ')');

            return Command::SUCCESS;
        }

        $campaign = new Campaign(100001, 'Demo кроссовки');
        $campaign->setBiddingEnabled(true);
        $campaign->setSeedNmId(987654321);
        $campaign->setRestrictUpIfRoasBelow('3.0');
        $campaign->setAllowUpIfRoasAbove('5.0');

        $campaign->addCluster(new Cluster($campaign, 987654321, 'кроссовки мужские', 10000));
        $campaign->addCluster(new Cluster($campaign, 987654321, 'кроссовки черные', 10000));
        $campaign->addCluster(new Cluster($campaign, 987654321, 'кроссовки бег', 10000));

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        $io->success('Demo campaign created with id=' . $campaign->getId());

        return Command::SUCCESS;
    }
}
