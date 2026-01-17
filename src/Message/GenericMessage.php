<?php
declare(strict_types=1);

namespace App\Message;

final class GenericMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $jobName,
        public readonly array $payload,
    ) {
    }
}
