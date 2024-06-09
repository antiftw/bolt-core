<?php

declare(strict_types=1);

namespace Bolt\Command;

use Bolt\Utils\ListFormatHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand(name: 'bolt:update-list-format', description: 'Update Bolt\'s cached ListFormat data.')]
class UpdateListFormatCommand extends Command
{
    protected static string $defaultName = 'bolt:update-list-format';

    public function __construct(private readonly ListFormatHelper $listFormatHelper)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Update Bolt\'s cached ListFormat data. ')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> updates clears the `list_format` and `title` columns in the database.
HELP
            );
    }

    /**
     * This method is executed after initialize(). It usually contains the logic
     * to execute to complete this command task.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->text('// Updating the Bolt Content `list_format` and `title` columns.');

        $amount = 10000;

        $success = $this->listFormatHelper->updateColumns($amount);

        if ($success) {
            $io->success('Rows updated successfully.');
        } else {
            $io->warning('Not all the rows could be cleared successfully. Remove them manually');
        }

        return Command::SUCCESS;
    }
}
