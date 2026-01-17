<?php
declare(strict_types=1);

namespace App\Message;

interface JobValidationProbeInterface
{
    /**
     * @param array<mixed> $payload
     */
    public function ok(string $jobName, array $payload): void;

    /**
     * @param array<mixed> $payload
     */
    public function failed(string $jobName, array $payload, string $reason): void;
}
