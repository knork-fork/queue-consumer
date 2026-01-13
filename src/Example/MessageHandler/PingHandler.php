<?php
declare(strict_types=1);

namespace App\Example\MessageHandler;

use App\Example\Message\Ping;
use App\Logger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PingHandler
{
    public function __invoke(Ping $m): void
    {
        Logger::info("Received Ping: {$m->text}", 'consumer');

        // throw new Exception("Simulated failure for Ping: {$m->text}");
    }
}
