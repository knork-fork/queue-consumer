<?php
declare(strict_types=1);

namespace App\Tests\Config;

use App\Config\JobConfigResolver;
use App\Message\GenericMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
final class JobConfigResolverTest extends TestCase
{
    /* @phpstan-ignore-next-line PHPStan is lying about this property not being initialized because it doesn't see setUp() */
    private JobConfigResolver $jobConfigResolver;

    protected function setUp(): void
    {
        $this->jobConfigResolver = new JobConfigResolver('test');
    }

    public function testGetJobByNameForUndefinedJobThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job config not found for job name: undefined-job-name');
        $this->jobConfigResolver->getJobByName('undefined-job-name');
    }

    public function testGetJobByNameForJobWithNoInput(): void
    {
        $job = $this->jobConfigResolver->getJobByName('test-job-no-input');
        self::assertSame('test-job-no-input', $job->name);
        self::assertSame('GET', $job->method);
        self::assertSame('http://queue-consumer-dummy-response:5678', $job->url);
        self::assertSame([], $job->queryUrlKeys);
        self::assertSame([], $job->jsonBodyKeys);
        self::assertSame([], $job->requiredInputKeys);
        self::assertSame('test', $job->logSuffix);
        self::assertSame(200, $job->successStatusCode);
    }

    public function testGetJobByNameForJobWithInputs(): void
    {
        $job = $this->jobConfigResolver->getJobByName('test-job-post-mixed-inputs');
        self::assertSame('test-job-post-mixed-inputs', $job->name);
        self::assertSame('POST', $job->method);
        self::assertSame('http://queue-consumer-dummy-response:5678', $job->url);
        self::assertSame(['query_url_key', 'another_query_key'], $job->queryUrlKeys);
        self::assertSame(['json_1', 'json_2', 'json_3'], $job->jsonBodyKeys);
        self::assertSame(['query_url_key', 'json_1', 'json_2'], $job->requiredInputKeys);
        self::assertSame('test', $job->logSuffix);
        self::assertSame(200, $job->successStatusCode);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideCheckIfMessagePayloadMatchesJobConfigForValidPayloadsReturnsTrueCases')]
    public function testCheckIfMessagePayloadMatchesJobConfigForValidPayloadsReturnsTrue(string $jobName, array $payload): void
    {
        $message = new GenericMessage($jobName, $payload);

        self::assertTrue(
            $this->jobConfigResolver->doesMessagePayloadMatchesJobConfig($message)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function provideCheckIfMessagePayloadMatchesJobConfigForValidPayloadsReturnsTrueCases(): iterable
    {
        return [
            ['test-job-no-input', []],
            ['test-job-post-mixed-inputs', [
                'query_url_key' => 'value1',
                'json_1' => 'value3',
                'json_2' => 'value4',
            ]],
            ['test-job-post-mixed-inputs', [
                'query_url_key' => 'value1',
                'another_query_key' => 'value2',
                'json_1' => 'value3',
                'json_2' => 'value4',
                'json_3' => 'value5',
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideCheckIfMessagePayloadMatchesJobConfigForInvalidPayloadsReturnsFalseCases')]
    public function testCheckIfMessagePayloadMatchesJobConfigForInvalidPayloadsReturnsFalse(string $jobName, array $payload): void
    {
        $message = new GenericMessage($jobName, $payload);

        self::assertFalse(
            $this->jobConfigResolver->doesMessagePayloadMatchesJobConfig($message)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function provideCheckIfMessagePayloadMatchesJobConfigForInvalidPayloadsReturnsFalseCases(): iterable
    {
        return [
            // Invalid job name
            ['undefined-job-name', []],
            // Too many inputs
            ['test-job-no-input', ['unexpected_key' => 'value']],
            // Missing inputs
            ['test-job-post-mixed-inputs', []],
            // Missing one required input
            ['test-job-post-mixed-inputs', [
                'another_query_key' => 'value2',
                'json_1' => 'value3',
                'json_2' => 'value4',
                'json_3' => 'value5',
            ]],
        ];
    }
}
