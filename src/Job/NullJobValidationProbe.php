<?php
declare(strict_types=1);

namespace App\Job;

final class NullJobValidationProbe implements JobValidationProbeInterface
{
    public function ok(string $jobName, array $payload): void
    {
    }

    public function failed(string $jobName, array $payload, string $reason): void
    {
    }
}
