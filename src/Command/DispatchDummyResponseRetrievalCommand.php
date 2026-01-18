<?php
declare(strict_types=1);

namespace App\Command;

use App\Message\GenericMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

// Try out with: docker exec queue-consumer-php-fpm php bin/console app:dispatch-dummy-response
// tail -f var/logs/php-fpm/php_dummy_response.log to see output
#[AsCommand(name: 'app:dispatch-dummy-response')]
final class DispatchDummyResponseRetrievalCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobName = 'dummy-response-retrieval';
        $routingKey = str_replace('-', '.', $jobName);

        $this->bus->dispatch(new GenericMessage(
            jobName: $jobName,
            payload: [
                'query_url_key' => 'value1',
                'json_1' => 'value3',
                'json_2' => 'value4',
            ]
        ), [new AmqpStamp($routingKey)]);
        $output->writeln('Dispatched');

        return Command::SUCCESS;
    }
}
