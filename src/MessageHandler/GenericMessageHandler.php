<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Config\JobConfigResolver;
use App\Logger;
use App\Message\GenericMessage;
use App\Message\JobValidationProbeInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenericMessageHandler
{
    public function __construct(
        private JobConfigResolver $resolver,
        private JobValidationProbeInterface $probe,
    ) {
    }

    public function __invoke(GenericMessage $m): void
    {
        try {
            $job = $this->resolver->getJobByName($m->jobName);
        } catch (RuntimeException $e) {
            $this->probe->failed($m->jobName, $m->payload, 'Job config not found');
            Logger::error("Invalid Generic Message: jobName={$m->jobName}, payload=" . json_encode($m->payload), 'default');

            return;
        }

        if (!$this->resolver->doesMessagePayloadMatchesJobConfig($m)) {
            $this->probe->failed($m->jobName, $m->payload, 'Payload does not match job config');
            Logger::error("Invalid Generic Message: jobName={$m->jobName}, payload=" . json_encode($m->payload), $job->logSuffix);

            return;
        }

        // TO-DO: curl here to the defined URL with defined method, query params and JSON body

        $this->probe->ok($m->jobName, $m->payload);
        Logger::info("Processed Generic Message successfully: jobName={$m->jobName}, payload=" . json_encode($m->payload), $job->logSuffix);
    }
}
