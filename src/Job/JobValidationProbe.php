<?php
declare(strict_types=1);

namespace App\Job;

final class JobValidationProbe implements JobValidationProbeInterface
{
    public int $ok = 0;
    public int $failed = 0;
    /**
     * @var array<string, mixed>
     */
    public array $last = [];

    public function ok(string $jobName, array $payload): void
    {
        ++$this->ok;
        $this->last = ['jobName' => $jobName, 'payload' => $payload];
    }

    public function failed(string $jobName, array $payload, string $reason): void
    {
        ++$this->failed;
        $this->last = ['jobName' => $jobName, 'payload' => $payload, 'reason' => $reason];
    }
}
