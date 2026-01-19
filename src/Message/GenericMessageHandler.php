<?php
declare(strict_types=1);

namespace App\Message;

use App\Config\JobConfigResolver;
use App\Job\JobValidationProbeInterface;
use App\Logger;
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

            // No exception re-throwing to prevent message retrying
            return;
        }

        if (!$this->resolver->doesMessagePayloadMatchesJobConfig($m)) {
            $this->probe->failed($m->jobName, $m->payload, 'Payload does not match job config');
            Logger::error("Invalid Generic Message: jobName={$m->jobName}, payload=" . json_encode($m->payload), $job->logSuffix);

            // No exception re-throwing to prevent message retrying
            return;
        }

        try {
            $job->loadCallbacks($this->resolver);
        } catch (RuntimeException $e) {
            $this->probe->failed($m->jobName, $m->payload, 'Failed to load job callbacks: ' . $e->getMessage());
            Logger::error("Failed to load job callbacks: jobName={$m->jobName}, payload=" . json_encode($m->payload) . ', error=' . $e->getMessage(), $job->logSuffix);

            // No exception re-throwing to prevent message retrying
            return;
        }

        $job->do($this->probe, $m->payload);
    }
}
