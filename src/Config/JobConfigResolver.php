<?php
declare(strict_types=1);

namespace App\Config;

use App\Job\Job;
use App\Message\GenericMessage;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class JobConfigResolver
{
    private const JOBS_CONFIG_PATH = '/application/config/jobs/';

    /** @var array<string, Job>|null */
    private ?array $jobs = null;

    public function __construct(private readonly string $env)
    {
        $this->loadJobsOnce();
    }

    public function getJobByName(string $jobName): Job
    {
        if (!isset($this->jobs[$jobName])) {
            throw new RuntimeException("Job config not found for job name: {$jobName}");
        }

        return $this->jobs[$jobName];
    }

    public function doesMessagePayloadMatchesJobConfig(GenericMessage $message): bool
    {
        if (!isset($this->jobs[$message->jobName])) {
            return false;
        }

        $job = $this->getJobByName($message->jobName);
        $payload = $message->payload;

        // Check if all required keys are present in the payload
        foreach ($job->requiredInputKeys as $requiredKey) {
            if (!\array_key_exists($requiredKey, $payload)) {
                return false;
            }
        }

        return true;
    }

    private function loadJobsOnce(): void
    {
        if ($this->jobs !== null) {
            return;
        }

        $files = $this->getConfigFiles();

        $jobs = [];
        foreach ($files as $file) {
            $parsed = Yaml::parseFile($file);
            if (!\is_array($parsed) || !\array_key_exists('jobs', $parsed) || !\is_array($parsed['jobs'])) {
                throw new RuntimeException("Invalid YAML (not a map): {$file}");
            }
            $parsedJobs = $parsed['jobs'];

            $jobsFromFile = [];
            foreach ($parsedJobs as $jobName => $jobConfig) {
                if (!\is_array($jobConfig) || !\array_key_exists('request', $jobConfig) || !\is_array($jobConfig['request'])) {
                    throw new RuntimeException("Invalid job config for job '{$jobName}' in file: {$file}");
                }
                $request = $jobConfig['request'];

                $method = $request['method'] ?? null;
                $url = $request['url'] ?? null;
                $queryUrlKeys = $request['query_url_from'] ?? null;
                $jsonBodyKeys = $request['json_body_from'] ?? null;
                $requiredInputKeys = $request['required'] ?? null;
                if (!\is_string($method)
                    || !\is_string($url)
                    || ($queryUrlKeys !== null && !\is_array($queryUrlKeys))
                    || ($jsonBodyKeys !== null && !\is_array($jsonBodyKeys))
                    || ($requiredInputKeys !== null && !\is_array($requiredInputKeys))) {
                    throw new RuntimeException("Invalid request config for job '{$jobName}' in file: {$file}");
                }

                $jobsFromFile[$jobName] = new Job(
                    name: (string) $jobName,
                    method: $method,
                    url: $url,
                    /* @phpstan-ignore-next-line PHPStan is overzealous here, but we don't want to lower overall reporting level */
                    queryUrlKeys: $queryUrlKeys,
                    /* @phpstan-ignore-next-line PHPStan is overzealous here, but we don't want to lower overall reporting level */
                    jsonBodyKeys: $jsonBodyKeys,
                    /* @phpstan-ignore-next-line PHPStan is overzealous here, but we don't want to lower overall reporting level */
                    requiredInputKeys: $requiredInputKeys,
                    logSuffix: $jobConfig['log_suffix'] ?? $jobName,
                    successStatusCode: $jobConfig['success']['status_code'] ?? 200,
                );
            }

            $jobs = array_merge($jobs, $jobsFromFile);
        }

        $this->jobs = $jobs;
    }

    /**
     * @return string[]
     */
    private function getConfigFiles(): array
    {
        if ($this->env === 'test') {
            return [self::JOBS_CONFIG_PATH . 'test.yaml'];
        }

        $filesInJobsFolder = glob(self::JOBS_CONFIG_PATH . '*.yaml');
        if ($filesInJobsFolder === false) {
            throw new RuntimeException('Failed to read job config files from folder: ' . self::JOBS_CONFIG_PATH);
        }

        $files = [];
        foreach ($filesInJobsFolder as $file) {
            if ($this->env !== 'test' && $file === self::JOBS_CONFIG_PATH . 'test.yaml') {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }
}
