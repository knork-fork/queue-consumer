<?php
declare(strict_types=1);

namespace App\Job;

use App\Config\JobConfigResolver;
use App\Logger;
use RuntimeException;

final class Job
{
    private ?self $onStartCallback = null;
    private ?self $onSuccessCallback = null;
    private ?self $onFailCallback = null;

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
        public readonly ?string $onStartCallbackName = null,
        public readonly ?string $onSuccessCallbackName = null,
        public readonly ?string $onFailCallbackName = null,
    ) {
    }

    public function loadCallbacks(JobConfigResolver $resolver): void
    {
        if ($this->onStartCallbackName !== null) {
            $this->onStartCallback = $resolver->getJobByName($this->onStartCallbackName);
            $this->onStartCallback->loadCallbacks($resolver);
        }

        if ($this->onSuccessCallbackName !== null) {
            $this->onSuccessCallback = $resolver->getJobByName($this->onSuccessCallbackName);
            $this->onSuccessCallback->loadCallbacks($resolver);
        }

        if ($this->onFailCallbackName !== null) {
            $this->onFailCallback = $resolver->getJobByName($this->onFailCallbackName);
            $this->onFailCallback->loadCallbacks($resolver);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function do(JobValidationProbeInterface $probe, array $payload): void
    {
        Logger::info("Starting job request: jobName={$this->name}, payload=" . json_encode($payload), $this->logSuffix);

        // onStart callback, if defined
        try {
            if ($this->onStartCallback !== null) {
                $this->onStartCallback->do($probe, $payload);
            }
        } catch (RuntimeException $e) {
            $this->fail($probe, $payload, $e);
        }

        // Main part of the job
        try {
            $this->makeRequest($payload);
        } catch (RuntimeException $e) {
            // Logging is done outside of fail() to prevent each callback chain member from logging the same message
            $probe->failed($this->name, $payload, $e->getMessage());
            Logger::error("Job request failed: jobName={$this->name}, payload=" . json_encode($payload) . ', error=' . $e->getMessage(), $this->logSuffix);

            // Add error message to payload to pass it to onFail callback, in case onFail callback will be updating status
            $payload['__error_message'] = $e->getMessage();

            $this->fail($probe, $payload, $e);
        }

        // onSuccess callback, if defined
        try {
            if ($this->onSuccessCallback !== null) {
                $this->onSuccessCallback->do($probe, $payload);
            }
        } catch (RuntimeException $e) {
            $this->fail($probe, $payload, $e);
        }

        $probe->ok($this->name, $payload);
        Logger::info("Job request completed successfully: jobName={$this->name}, payload=" . json_encode($payload), $this->logSuffix);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fail(JobValidationProbeInterface $probe, array $payload, RuntimeException $e): void
    {
        // onFail callback, if defined
        if ($this->onFailCallback !== null) {
            try {
                $this->onFailCallback->do($probe, $payload);
            } catch (RuntimeException $e) {
                // onFail callback can fail too, but we ignore that failure as we're already in a failure state
            }
        }

        throw new RuntimeException('Job request failed: ' . $e->getMessage());
    }

    /**
     * @param array<string, mixed> $payload
     * @param mixed[]              $headers
     */
    private function makeRequest(array $payload, array $headers = []): void
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
            throw new RuntimeException('Failed to initialize cURL');
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
            throw new RuntimeException('cURL request failed with error: ' . curl_error($ch));
        }

        /** @var int $statusCode */
        $statusCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        if ($statusCode !== $this->successStatusCode) {
            throw new RuntimeException("Unexpected status code: {$statusCode}");
        }
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
            throw new RuntimeException('Failed to encode JSON body');
        }

        return $json;
    }
}
