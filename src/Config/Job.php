<?php
declare(strict_types=1);

namespace App\Config;

final class Job
{
    /**
     * @param string[] $queryUrlKeys
     * @param string[] $jsonBodyKeys
     * @param string[] $requiredInputKeys
     */
    public function __construct(
        public readonly string $name,
        public readonly string $method,
        public readonly string $url,
        public readonly array $queryUrlKeys,
        public readonly array $jsonBodyKeys,
        public readonly array $requiredInputKeys,
        public readonly string $logSuffix,
        public readonly int $successStatusCode,
    ) {
    }
}
