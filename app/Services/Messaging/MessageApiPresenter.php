<?php

namespace App\Services\Messaging;

use App\Models\Messages;
use Illuminate\Support\Carbon;

/**
 * Builds the public `message` object shared by the v1 Messages API and the
 * `message.received` / `message.sent` webhooks, so both always carry the
 * same shape.
 */
class MessageApiPresenter
{
    public const DIRECTION_MAP = [
        'in' => 'inbound',
        'out' => 'outbound',
    ];

    /**
     * @param  string|null  $extensionNumber  The extension number for the
     *         message's extension_uuid, when the caller already resolved it.
     */
    public function present(Messages $message, ?string $extensionNumber = null): array
    {
        $domainUuid = (string) $message->domain_uuid;
        $messageUuid = (string) $message->message_uuid;
        $direction = self::DIRECTION_MAP[$message->direction] ?? (string) $message->direction;

        return [
            'object' => 'message',
            'message_uuid' => $messageUuid,
            'domain_uuid' => $domainUuid,
            'direction' => $direction,
            'type' => (string) $message->type,
            'from' => $message->source,
            'to' => $message->destination,
            'extension_uuid' => $message->extension_uuid,
            'extension' => $extensionNumber,
            'body' => $message->message ?? '',
            'media' => $this->presentMedia($message, $domainUuid, $messageUuid),
            'status' => $message->status,
            'provider' => data_get($message->delivery_meta, 'provider.name')
                ?? data_get($message->delivery_meta, 'outbound.provider.name'),
            'provider_message_id' => $message->reference_id,
            'created_at' => $this->isoTimestamp($message->getRawOriginal('created_at') ?? $message->created_at),
            'updated_at' => $this->isoTimestamp($message->getRawOriginal('updated_at') ?? $message->updated_at),
        ];
    }

    public function mediaUrl(string $domainUuid, string $messageUuid, int $index): string
    {
        return url("/api/v1/domains/{$domainUuid}/messages/{$messageUuid}/media/{$index}");
    }

    private function presentMedia(Messages $message, string $domainUuid, string $messageUuid): array
    {
        $media = $message->media;
        if (! is_array($media)) {
            return [];
        }

        $items = [];
        foreach (array_values($media) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'index' => $index,
                'content_type' => $item['mime_type'] ?? null,
                'filename' => $item['original_name'] ?? $item['stored_name'] ?? null,
                'size' => isset($item['size']) ? (int) $item['size'] : null,
                'url' => $this->mediaUrl($domainUuid, $messageUuid, $index),
            ];
        }

        return $items;
    }

    private function isoTimestamp($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, 'UTC')->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
