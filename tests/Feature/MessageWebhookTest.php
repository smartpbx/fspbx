<?php

namespace Tests\Feature;

use App\Jobs\CallWebhooks\SafeCallWebhookJob;
use App\Models\CallWebhookSubscription;
use App\Models\Messages;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\MessageWebhookService;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MessagingSqliteSchema;
use Tests\TestCase;

class MessageWebhookTest extends TestCase
{
    use MessagingSqliteSchema;

    private const DOMAIN_A = '11111111-1111-4111-8111-111111111111';
    private const DOMAIN_B = '22222222-2222-4222-8222-222222222222';
    private const EXT_A1 = 'aaaaaaaa-0000-4000-8000-000000000001';
    private const SECRET_A = 'test-signing-secret-a';
    private const SECRET_B = 'test-signing-secret-b';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMessagingSchema();

        $this->insertDomain(self::DOMAIN_A, 'a.example.com');
        $this->insertDomain(self::DOMAIN_B, 'b.example.com');
        $this->insertExtension(self::DOMAIN_A, self::EXT_A1, '1001');

        Queue::fake();
    }

    public function test_inbound_message_emits_a_signed_message_received_webhook(): void
    {
        $this->subscribe(self::DOMAIN_A, self::SECRET_A, ['message.received']);
        $this->subscribe(self::DOMAIN_B, self::SECRET_B, ['message.received', 'message.sent']);

        $message = app(MessageRepository::class)->storeInbound(
            domainUuid: self::DOMAIN_A,
            extensionUuid: self::EXT_A1,
            source: '+12025550111',
            destination: '+12025550100',
            text: 'Is the phone fixed?',
            type: 'sms',
            providerName: 'example',
            providerReferenceId: 'prov-1',
        );

        $jobs = $this->pushedWebhookJobs();
        $this->assertCount(1, $jobs, 'exactly one delivery, to domain A only');
        $job = $jobs[0];

        $this->assertStringStartsWith('https://a.example.com/', $job->webhookUrl);
        $this->assertSame('message.received', $job->payload['type']);
        $this->assertSame($job->payload['id'], $job->headers['X-FS-PBX-Event-ID']);
        $this->assertSame('message.received', $job->headers['X-FS-PBX-Event-Type']);
        $this->assertSame(MessageWebhookService::DELIVERY_TRIES, $job->tries);

        $data = $job->payload['data'];
        $this->assertSame('message', $data['object']);
        $this->assertSame($message->message_uuid, $data['message_uuid']);
        $this->assertSame(self::DOMAIN_A, $data['domain_uuid']);
        $this->assertSame('inbound', $data['direction']);
        $this->assertSame('+12025550111', $data['from']);
        $this->assertSame('1001', $data['extension']);
        $this->assertSame('Is the phone fixed?', $data['body']);
        $this->assertSame('prov-1', $data['provider_message_id']);

        // Signature = hex HMAC-SHA256 of the exact JSON body, keyed by the
        // domain's signing secret.
        $body = json_encode($job->payload);
        $this->assertSame(hash_hmac('sha256', $body, self::SECRET_A), $job->headers['Signature']);
        $this->assertNotSame(hash_hmac('sha256', $body, self::SECRET_B), $job->headers['Signature']);
    }

    public function test_successful_outbound_send_emits_message_sent_and_a_failure_does_not(): void
    {
        $this->subscribe(self::DOMAIN_A, self::SECRET_A, ['message.sent']);

        $sent = $this->insertMessage([
            'domain_uuid' => self::DOMAIN_A,
            'extension_uuid' => self::EXT_A1,
            'direction' => 'out',
            'status' => 'queued',
            'source' => '+12025550100',
            'destination' => '+12025550111',
        ]);
        $failed = $this->insertMessage([
            'domain_uuid' => self::DOMAIN_A,
            'extension_uuid' => self::EXT_A1,
            'direction' => 'out',
            'status' => 'queued',
        ]);

        $repository = app(MessageRepository::class);
        $repository->applyOutboundSendResult($sent, 'example', $this->sendResult(true, 'success', 'prov-out-1'));
        $repository->applyOutboundSendResult($failed, 'example', $this->sendResult(false, 'failed', null));

        $jobs = $this->pushedWebhookJobs();
        $this->assertCount(1, $jobs);
        $this->assertSame('message.sent', $jobs[0]->payload['type']);
        $this->assertSame($sent->message_uuid, $jobs[0]->payload['data']['message_uuid']);
        $this->assertSame('outbound', $jobs[0]->payload['data']['direction']);
        $this->assertSame('prov-out-1', $jobs[0]->payload['data']['provider_message_id']);
    }

    public function test_no_delivery_without_an_opted_in_subscription(): void
    {
        // Call-only subscription: message events are opt-in.
        $this->subscribe(self::DOMAIN_A, self::SECRET_A, CallWebhookSubscription::EVENTS);

        $message = $this->insertMessage(['domain_uuid' => self::DOMAIN_A]);
        $this->assertFalse(app(MessageWebhookService::class)->emit('message.received', $message));

        $this->subscribe(self::DOMAIN_B, self::SECRET_B, ['message.received'], enabled: false);
        $messageB = $this->insertMessage(['domain_uuid' => self::DOMAIN_B]);
        app(MessageWebhookService::class)->received($messageB);

        $this->assertCount(0, $this->pushedWebhookJobs());
    }

    public function test_non_sms_rows_are_never_emitted(): void
    {
        $this->subscribe(self::DOMAIN_A, self::SECRET_A, ['message.received']);

        $chat = $this->insertMessage(['domain_uuid' => self::DOMAIN_A, 'type' => 'chat']);

        $this->assertFalse(app(MessageWebhookService::class)->emit('message.received', $chat));
        $this->assertCount(0, $this->pushedWebhookJobs());
    }

    public function test_event_id_is_stable_per_message_and_event(): void
    {
        $service = app(MessageWebhookService::class);
        $uuid = 'a0000000-0000-4000-8000-000000000001';

        $this->assertSame($service->eventId('message.sent', $uuid), $service->eventId('message.sent', $uuid));
        $this->assertNotSame($service->eventId('message.sent', $uuid), $service->eventId('message.received', $uuid));
    }

    public function test_message_events_are_accepted_by_subscription_validation(): void
    {
        $this->assertContains('message.received', CallWebhookSubscription::ALL_EVENTS);
        $this->assertContains('message.sent', CallWebhookSubscription::ALL_EVENTS);
        $this->assertNotContains('message.received', CallWebhookSubscription::EVENTS);
    }

    /** @return SafeCallWebhookJob[] */
    private function pushedWebhookJobs(): array
    {
        return Queue::pushed(SafeCallWebhookJob::class)->all();
    }

    private function subscribe(string $domainUuid, string $secret, array $events, bool $enabled = true): void
    {
        CallWebhookSubscription::query()->create([
            'domain_uuid' => $domainUuid,
            'endpoint_url' => 'https://' . ($domainUuid === self::DOMAIN_A ? 'a' : 'b') . '.example.com/hooks',
            'signing_secret' => $secret,
            'enabled' => $enabled,
            'events' => $events,
        ]);
    }

    private function sendResult(bool $success, string $status, ?string $reference): OutboundSendResultData
    {
        return new OutboundSendResultData(
            success: $success,
            status: $status,
            providerReferenceId: $reference,
            error: $success ? null : 'rejected',
            providerResponse: [],
        );
    }
}
