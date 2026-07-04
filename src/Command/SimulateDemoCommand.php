<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Time\GameDate;
use DateTimeImmutable;

use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Demonstrates the bare day-tick loop (Phase 0-1, step "tick tout seul").
 *
 * Advances the in-game calendar one day at a time and prints the date/season,
 * exercising the pure {@see GameDate} domain object with no database or UI.
 */
#[AsCommand(
    name: 'app:simulate:demo',
    description: 'Advance the in-game calendar day by day and print each tick.',
)]
final class SimulateDemoCommand extends Command
{
    private const string DEFAULT_EPOCH = '2025-01-01';
    private const int DEFAULT_DAYS = 14;

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days to simulate', (string) self::DEFAULT_DAYS)
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Epoch date (Y-m-d)', self::DEFAULT_EPOCH);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $io->error('The --days option must be a positive integer.');

            return Command::INVALID;
        }

        $epochOption = (string) $input->getOption('from');
        $epoch = DateTimeImmutable::createFromFormat('!Y-m-d', $epochOption);
        if (false === $epoch) {
            $io->error(sprintf('Invalid --from date "%s" (expected Y-m-d).', $epochOption));

            return Command::INVALID;
        }

        $io->title(sprintf('EcoSim — %d jours de simulation depuis %s', $days, $epochOption));

        $rows = [];
        $date = GameDate::epoch($epoch);
        for ($tick = 0; $tick < $days; ++$tick) {
            $rows[] = [
                $date->dayIndex(),
                $date->format('Y-m-d'),
                $date->format('D'),
                $date->dayOfYear(),
                $date->season()->label(),
            ];
            $date = $date->next();
        }

        $io->table(['Jour', 'Date', 'Jour sem.', 'Jour/an', 'Saison'], $rows);
        $io->success(sprintf('Simulation terminée : %d jours joués.', $days));

        return Command::SUCCESS;
    }
}
