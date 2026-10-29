<?php

declare(strict_types=1);

namespace Skyyware\Starcode\Command;

use Skyyware\Starcode\Protection\DatabaseProofStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'starcode:cleanup', description: 'Remove expired Starcode proofs and rate records.')]
final class CleanupCommand extends Command
{
    public function __construct(private readonly DatabaseProofStore $store)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count expired rows without deleting them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cutoff = time();
        if ($input->getOption('dry-run')) {
            $io->text($this->store->countExpired($cutoff) . ' expired rows; no changes made.');
            return self::SUCCESS;
        }

        $removed = 0;
        for ($batch = 0; $batch < 100; ++$batch) {
            $count = $this->store->deleteExpired($cutoff, 1000);
            $removed += $count;
            if ($count === 0) {
                break;
            }
        }

        $remaining = $this->store->countExpired($cutoff);
        $io->text(sprintf('%d expired rows removed; %d remaining at cutoff %d.', $removed, $remaining, $cutoff));
        if ($remaining !== 0) {
            $io->error('Cleanup batch budget reached. Run the command again to complete cleanup.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
