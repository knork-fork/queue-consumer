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
    public function testJobWithNoPayloadIsAccepted(): void
    {
        self::bootKernel();
        /** @var MessageBus */
        $bus = self::getContainer()->get(MessageBusInterface::class);

        $bus->dispatch(new GenericMessage(
            jobName: 'test-job-no-input',
            payload: []
        ));

        /** @var JobValidationProbe $probe */
        $probe = self::getContainer()->get(JobValidationProbe::class);

        self::assertSame(1, $probe->ok);
        self::assertSame(0, $probe->failed);
        self::assertSame('test-job-no-input', $probe->last['jobName']);
        self::assertSame([], $probe->last['payload']);
    }

    public function testValidPayloadIsAccepted(): void
    {
        self::bootKernel();
        /** @var MessageBus */
        $bus = self::getContainer()->get(MessageBusInterface::class);

        $bus->dispatch(new GenericMessage(
            jobName: 'test-job-post-mixed-inputs',
            payload: [
                'query_url_key' => 'value1',
                'json_1' => 'value3',
                'json_2' => 'value4',
            ]
        ));

        /** @var JobValidationProbe $probe */
        $probe = self::getContainer()->get(JobValidationProbe::class);

        self::assertSame(1, $probe->ok);
        self::assertSame(0, $probe->failed);
        self::assertSame('test-job-post-mixed-inputs', $probe->last['jobName']);

        self::assertIsArray($probe->last['payload']);
        self::assertArrayHasKey('query_url_key', $probe->last['payload']);
        self::assertSame('value1', $probe->last['payload']['query_url_key']);
    }

    public function testPayloadWithMissingFieldsFails(): void
    {
        self::bootKernel();
        /** @var MessageBus */
        $bus = self::getContainer()->get(MessageBusInterface::class);

        $bus->dispatch(new GenericMessage(
            jobName: 'test-job-post-mixed-inputs',
            payload: [
                'query_url_key' => 'value1',
                // json_1 missing
                'json_2' => 'value4',
            ]
        ));

        /** @var JobValidationProbe $probe */
        $probe = self::getContainer()->get(JobValidationProbe::class);

        self::assertSame(0, $probe->ok);
        self::assertSame(1, $probe->failed);
        self::assertSame('Payload does not match job config', $probe->last['reason']);
    }

    public function testWrongStatusCodeFails(): void
    {
        self::bootKernel();
        /** @var MessageBus */
        $bus = self::getContainer()->get(MessageBusInterface::class);

        try {
            $bus->dispatch(new GenericMessage(
                jobName: 'test-job-wrong-status-code',
                payload: []
            ));

            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Job request failed', $e->getMessage());
        }

        /** @var JobValidationProbe $probe */
        $probe = self::getContainer()->get(JobValidationProbe::class);

        self::assertSame(0, $probe->ok);
        self::assertSame(1, $probe->failed);
        self::assertSame('Request failed', $probe->last['reason']);
    }
}
