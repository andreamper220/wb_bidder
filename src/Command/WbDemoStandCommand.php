<?php

namespace App\Command;

use App\Demo\DemoStandService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wb:demo-stand',
    description: 'Create demo stand: seed campaign, sync mock stats, calculate bid decisions',
)]
final class WbDemoStandCommand extends Command
{
    public function __construct(
        private readonly DemoStandService $demoStandService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Remove existing demo campaign and recreate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reset = (bool) $input->getOption('reset');

        $result = $this->demoStandService->run($reset);

        if ($result->reset) {
            $io->writeln('Removed existing demo campaign.');
        }

        if ($result->created) {
            $io->writeln('Created demo campaign id=' . $result->campaignId);
        } else {
            $io->writeln('Using existing demo campaign id=' . $result->campaignId);
        }

        $io->writeln('Synced stats from WB API (mock fixtures).');
        $io->writeln(sprintf('Calculated %d bid decisions (dry-run).', $result->decisionsCount));
        $io->writeln(sprintf(
            'Campaign mode: %s, ROAS: %s',
            $result->campaignMode->value,
            $result->roas ?? '—',
        ));

        $io->success('Demo stand ready. Open http://localhost:8080/admin');

        return Command::SUCCESS;
    }
}
