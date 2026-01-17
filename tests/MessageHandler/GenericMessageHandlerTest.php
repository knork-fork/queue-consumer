<?php
namespace App\Tests\MessageHandler;

use App\Message\JobValidationProbe;
use App\Message\GenericMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class GenericMessageHandlerTest extends KernelTestCase
{
    public function testJobWithNoPayloadIsAccepted(): void
    {
        self::bootKernel();
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
        self::assertSame('value1', $probe->last['payload']['query_url_key']);
    }

    public function testPayloadWithMissingFieldsFails(): void
    {
        self::bootKernel();
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
}
