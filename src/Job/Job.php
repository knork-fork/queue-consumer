<?php
declare(strict_types=1);

namespace App\Job;

use App\Logger;
use RuntimeException;

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

    /**
     * @param array<string, mixed> $payload
     */
    public function do(JobValidationProbeInterface $probe, array $payload): void
    {
        Logger::info("Starting job request: jobName={$this->name}, payload=" . json_encode($payload), $this->logSuffix);

        // Call on_start callback here

        if (!$this->makeRequest($payload)) {
            $probe->failed($this->name, $payload, 'Request failed');
            Logger::error("Job request failed: jobName={$this->name}, payload=" . json_encode($payload), $this->logSuffix);

            // Call on_fail callback here

            throw new RuntimeException('Job request failed');
        }

        // Call on_success callback here

        $probe->ok($this->name, $payload);
        Logger::info("Job request completed successfully: jobName={$this->name}, payload=" . json_encode($payload), $this->logSuffix);
    }

    /**
     * @param array<string, mixed> $payload
     * @param mixed[]              $headers
     */
    private function makeRequest(array $payload, array $headers = []): bool
    {
        $url = $this->url . $this->buildQueryUrl($payload);
        $url = empty($url) ? 'http://localhost' : $url;
        $method = empty($this->method) ? 'GET' : strtoupper($this->method);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_URL => $url,
            \CURLOPT_SSL_VERIFYHOST => 0,
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_POSTFIELDS => $this->buildPostBody($payload),
            \CURLOPT_HTTPHEADER => $headers,
            // Infinite timeout
            \CURLOPT_TIMEOUT => 0,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            Logger::error('cURL error: ' . curl_error($ch), $this->logSuffix);

            return false;
        }

        /** @var int $statusCode */
        $statusCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        return $statusCode === $this->successStatusCode;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildQueryUrl(array $payload): string
    {
        $queryParams = [];
        foreach ($this->queryUrlKeys as $key) {
            if (isset($payload[$key])) {
                $queryParams[$key] = $payload[$key];
            }
        }

        if (\count($queryParams) === 0) {
            return '';
        }

        return '?' . http_build_query($queryParams);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildPostBody(array $payload): string
    {
        $jsonBody = [];
        foreach ($this->jsonBodyKeys as $key) {
            if (isset($payload[$key])) {
                $jsonBody[$key] = $payload[$key];
            }
        }

        if (\count($jsonBody) === 0) {
            return '{}';
        }

        $json = json_encode($jsonBody);
        if ($json === false) {
            return '{}';
        }

        return $json;
    }
}
