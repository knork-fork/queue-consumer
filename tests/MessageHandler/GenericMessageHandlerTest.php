<?php
declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Job\JobValidationProbe;
use App\Message\GenericMessage;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class GenericMessageHandlerTest extends KernelTestCase
{
    public function testJobWithNoPayloadFinishesWithNoFails(): void
    {
        $this->dispatchJob(new GenericMessage(
            jobName: 'test-job-no-input',
            payload: []
        ));

        $probe = $this->getProbe();
        self::assertSame(1, $probe->ok);
        self::assertSame(0, $probe->failed);
        self::assertSame('test-job-no-input', $probe->last['jobName']);
        self::assertSame([], $probe->last['payload']);
    }

    public function testValidPayloadFinishesWithNoFails(): void
    {
        $this->dispatchJob(new GenericMessage(
            jobName: 'test-job-post-mixed-inputs',
            payload: [
                'query_url_key' => 'value1',
                'json_1' => 'value3',
                'json_2' => 'value4',
            ]
        ));

        $probe = $this->getProbe();
        self::assertSame(1, $probe->ok);
        self::assertSame(0, $probe->failed);
        self::assertSame('test-job-post-mixed-inputs', $probe->last['jobName']);

        self::assertIsArray($probe->last['payload']);
        self::assertArrayHasKey('query_url_key', $probe->last['payload']);
        self::assertSame('value1', $probe->last['payload']['query_url_key']);
    }

    public function testPayloadWithMissingFieldsFailsWithNoException(): void
    {
        $this->dispatchJob(new GenericMessage(
            jobName: 'test-job-post-mixed-inputs',
            payload: [
                'query_url_key' => 'value1',
                // json_1 missing
                'json_2' => 'value4',
            ]
        ));

        $probe = $this->getProbe();
        self::assertSame(0, $probe->ok);
        self::assertSame(1, $probe->failed);
        self::assertSame('Payload does not match job config', $probe->last['reason']);
    }

    public function testWrongStatusCodeFailsWithException(): void
    {
        try {
            $this->dispatchJob(new GenericMessage(
                jobName: 'test-job-wrong-status-code',
                payload: []
            ));

            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Job request failed', $e->getMessage());
        }

        $probe = $this->getProbe();
        self::assertSame(0, $probe->ok);
        self::assertSame(1, $probe->failed);
        self::assertSame('Unexpected status code: 200', $probe->last['reason']);
    }

    public function testJobWithCallbacksFinishesWithNoFails(): void
    {
        $this->dispatchJob(new GenericMessage(
            jobName: 'test-job-with-callbacks',
            payload: []
        ));

        $probe = $this->getProbe();
        // Main job + onStart + onSuccess = 3 successful steps
        self::assertSame(3, $probe->ok);
        self::assertSame(0, $probe->failed);
    }

    public function testJobWithOnFailCallbackFinishesWithException(): void
    {
        try {
            $this->dispatchJob(new GenericMessage(
                jobName: 'test-job-with-onfail-callback',
                payload: []
            ));

            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Job request failed', $e->getMessage());
        }

        $probe = $this->getProbe();
        // Main job failed, but onFail succeeded
        self::assertSame(1, $probe->ok);
        self::assertSame(1, $probe->failed);

        self::assertArrayHasKey('payload', $probe->last);
        self::assertIsArray($probe->last['payload']);
        self::assertArrayHasKey('__error_message', $probe->last['payload']);
        self::assertSame('Unexpected status code: 200', $probe->last['payload']['__error_message']);
    }

    public function testJobWithBrokenCallbacksFailsWithNoException(): void
    {
        $this->dispatchJob(new GenericMessage(
            jobName: 'test-job-with-broken-callbacks',
            payload: []
        ));

        $probe = $this->getProbe();
        self::assertSame(0, $probe->ok);
        self::assertSame(1, $probe->failed);
        self::assertIsString($probe->last['reason']);
        self::assertStringContainsString('Failed to load job callbacks', $probe->last['reason']);
    }

    public function testJobWithBrokenOnStartCallbackFailsWithException(): void
    {
        try {
            $this->dispatchJob(new GenericMessage(
                jobName: 'test-job-with-failing-onstart',
                payload: []
            ));

            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Job request failed', $e->getMessage());
        }

        $probe = $this->getProbe();
        // Main job did not run, onStart failed, onFail succeeded
        self::assertSame(1, $probe->ok);
        self::assertSame(1, $probe->failed);
    }

    public function testJobWithBrokenOnSuccessCallbackFailsWithException(): void
    {
        try {
            $this->dispatchJob(new GenericMessage(
                jobName: 'test-job-with-failing-onsuccess',
                payload: []
            ));

            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Job request failed', $e->getMessage());
        }

        $probe = $this->getProbe();
        // Main job succeeded, but didn't register to probe because onSuccess failed, onFail succeeded
        self::assertSame(1, $probe->ok);
        self::assertSame(1, $probe->failed);
    }

    public function testJobWithBrokenOnFailCallbackFailsWithException(): void
    {
        try {
            $this->dispatchJob(new GenericMessage(
                jobName: 'test-job-with-failing-onfail',
                payload: []
            ));

            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Job request failed', $e->getMessage());
        }

        $probe = $this->getProbe();
        // Main job failed and onFail both failed
        self::assertSame(0, $probe->ok);
        self::assertSame(2, $probe->failed);
    }

    public function testJobWithChainedCallbacksFinishesWithNoFails(): void
    {
        $this->dispatchJob(new GenericMessage(
            jobName: 'test-job-chain-start',
            payload: []
        ));

        $probe = $this->getProbe();
        self::assertSame(3, $probe->ok);
        self::assertSame(0, $probe->failed);
    }

    private function dispatchJob(GenericMessage $message): void
    {
        self::bootKernel();
        /** @var MessageBus */
        $bus = self::getContainer()->get(MessageBusInterface::class);

        $bus->dispatch($message);
    }

    private function getProbe(): JobValidationProbe
    {
        /** @var ?JobValidationProbe $probe */
        $probe = self::getContainer()->get(JobValidationProbe::class);
        if ($probe === null) {
            throw new RuntimeException('JobValidationProbe not found in container');
        }

        return $probe;
    }
}
