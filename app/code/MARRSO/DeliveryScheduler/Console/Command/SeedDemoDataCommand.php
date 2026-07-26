<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Console\Command;

use MARRSO\DeliveryScheduler\Model\Installer\DemoDataInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedDemoDataCommand extends Command
{
    public const NAME = 'marrso:delivery:seed-demo';

    public function __construct(
        private readonly DemoDataInstaller $demoDataInstaller
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(self::NAME);
        $this->setDescription('Seed MARRSO Delivery Scheduler demo data (Lima pickup, delivery and express slots).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Seeding MARRSO Delivery Scheduler demo data...</info>');

        try {
            $this->demoDataInstaller->install();
        } catch (\Throwable $exception) {
            $output->writeln('<error>Demo seed failed: ' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Demo data seeded successfully.</info>');
        $output->writeln('Pickup locations, pickup slots, delivery slots, express slots and holidays are ready.');
        $output->writeln('Use city <comment>Lima</comment> (or seeded districts) in checkout shipping address.');

        return Command::SUCCESS;
    }
}
