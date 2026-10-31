<?php

namespace App\Services\Messaging;

use App\Models\CallWebhookSubscription;
use App\Models\Extensions;
use App\Models\Messages;
use App\Services\CallWebhooks\CallWebhookDeliveryService;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Emits `message.received` and `message.sent` to the domain's webhook
 * subscription (the same per-domain subscription, signing secret and
 * delivery pipeline as the call webhooks).
 *
 * Event ids are derived from the message UUID and event type, so a repeat
 * emit for the same message (a provider retry, a resend) carries the same
 * id and the receiver can drop it.
 */
class MessageWebhookService
{
    /** First attempt plus retries at ~10s, ~100s, ~1000s and ~10000s. */
    public const DELIVERY_TRIES = 5;

    private const EVENT_ID_NAMESPACE = '0d5c3a7e-6f0b-4a51-9c3e-8f4f2b1d6a90';

    public function __construct(
        protected MessageApiPresenter $presenter,
        protected CallWebhookDeliveryService $delivery,
    ) {
    }

    public function received(Messages $message): void
    {
        $this->emit(CallWebhookSubscription::EVENT_MESSAGE_RECEIVED, $message);
    }

    public function sent(Messages $message): void
    {
        $this->emit(CallWebhookSubscription::EVENT_MESSAGE_SENT, $message);
    }

    /**
     * Never throws: a webhook problem must not break message handling.
     */
    public function emit(string $eventType, Messages $message): bool
    {
        try {
            $domainUuid = (string) $message->domain_uuid;
            if ($domainUuid === '' || ! in_array((string) $message->type, ['sms', 'mms'], true)) {
                return false;
            }

            $subscription = CallWebhookSubscription::query()
                ->where('domain_uuid', $domainUuid)
                ->where('enabled', true)
                ->first();

            if (! $subscription || ! $subscription->accepts($eventType)) {
                return false;
            }

            return $this->delivery->dispatch(
                $subscription,
                $this->payload($eventType, $message),
                self::DELIVERY_TRIES
            );
        } catch (Throwable $e) {
            logger()->warning('Message webhook emit failed', [
                'event_type' => $eventType,
                'message_uuid' => $message->message_uuid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function payload(string $eventType, Messages $message): array
    {
        $extensionNumber = null;
        if ($message->extension_uuid) {
            $extensionNumber = Extensions::query()
                ->toBase()
                ->where('domain_uuid', $message->domain_uuid)
                ->where('extension_uuid', $message->extension_uuid)
                ->value('extension');
        }

        return [
            'id' => $this->eventId($eventType, (string) $message->message_uuid),
            'type' => $eventType,
            'occurred_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
            'data' => $this->presenter->present($message, $extensionNumber !== null ? (string) $extensionNumber : null),
        ];
    }

    public function eventId(string $eventType, string $messageUuid): string
    {
        return Uuid::uuid5(self::EVENT_ID_NAMESPACE, "{$eventType}:{$messageUuid}")->toString();
    }
}
