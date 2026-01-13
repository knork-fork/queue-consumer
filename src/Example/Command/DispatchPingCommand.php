<?php
declare(strict_types=1);

namespace App\Example\Command;

use App\Example\Message\Ping;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

// Try out with: docker compose exec consumer php bin/console app:dispatch-ping "text here"
// tail var/logs/php_consumer.log to see output
#[AsCommand(name: 'app:dispatch-ping')]
final class DispatchPingCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('text', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $textInput */
        $textInput = $input->getArgument('text');
        $this->bus->dispatch(new Ping($textInput));
        $output->writeln('Dispatched');

        return Command::SUCCESS;
    }
}
