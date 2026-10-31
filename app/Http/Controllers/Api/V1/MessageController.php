<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Messages;
use App\Services\Auth\PermissionService;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\MessageApiPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MessageController extends Controller
{
    private const UUID_PATTERN = '/^[0-9a-fA-F-]{36}$/';

    /** Only carrier SMS/MMS are exposed; nothing else is stored as a message. */
    private const MESSAGE_TYPES = ['sms', 'mms'];

    public function __construct(
        protected MessageApiPresenter $presenter,
        protected PermissionService $authz,
    ) {
    }

    /**
     * List SMS/MMS messages
     *
     * Returns SMS and MMS messages for the specified domain, oldest first.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `messages_view` permission.
     * - Without `messages_view_as`, only messages on the caller's own
     *   extension are returned (same rule as the Messages page).
     *
     * Pagination (cursor-based):
     * - `limit` defaults to 100 (min 1, max 500).
     * - Results are ordered by `created_at` ascending, then `message_uuid`.
     * - If `has_more` is true, request the next page by passing
     *   `starting_after` equal to the last item's `message_uuid`.
     *
     * Optional filters:
     * - `extension` filters by extension number; `extension_uuid` by UUID.
     * - `direction` is `inbound` or `outbound`.
     * - `since` / `until` bound `created_at` (inclusive / exclusive). Each
     *   accepts epoch seconds or an ISO-8601 timestamp.
     * - `number` matches the external party (the `from` of an inbound
     *   message or the `to` of an outbound one) by trailing digits.
     *
     * @group Messages
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 500). Defaults to 100. Example: 100
     * @queryParam starting_after string Optional. Return results after this message UUID (cursor). Example: 2b1f0f51-7b0e-4b55-9a39-0c1c5f0b7e11
     * @queryParam extension string Optional. Filter by extension number. Example: 1001
     * @queryParam extension_uuid string Optional. Filter by extension UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @queryParam direction string Optional. `inbound` or `outbound`. Example: inbound
     * @queryParam since string Optional. Only messages created at or after this time (epoch seconds or ISO-8601). Example: 2026-10-01T00:00:00Z
     * @queryParam until string Optional. Only messages created before this time (epoch seconds or ISO-8601). Example: 1792886400
     * @queryParam number string Optional. External party number; digits are matched against the end of the stored E.164 number. Example: 2135551212
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/messages",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "object": "message",
     *       "message_uuid": "2b1f0f51-7b0e-4b55-9a39-0c1c5f0b7e11",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "direction": "inbound",
     *       "type": "mms",
     *       "from": "+12135551212",
     *       "to": "+12135550100",
     *       "extension_uuid": "c9a76140-0ca4-4ea3-95af-7e12c2ff0df5",
     *       "extension": "1001",
     *       "body": "Photo of the phone attached",
     *       "media": [
     *         {
     *           "index": 0,
     *           "content_type": "image/jpeg",
     *           "filename": "IMG_0001.jpg",
     *           "size": 48213,
     *           "url": "https://pbx.example.com/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/messages/2b1f0f51-7b0e-4b55-9a39-0c1c5f0b7e11/media/0"
     *         }
     *       ],
     *       "status": "received",
     *       "provider": "telnyx",
     *       "provider_message_id": "40318f1b-35a3-4cb5-a39e-34c4d1e6a3a8",
     *       "created_at": "2026-10-01T14:03:11Z",
     *       "updated_at": "2026-10-01T14:03:11Z"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid direction" {"error":{"type":"invalid_request_error","message":"Invalid direction. Use inbound or outbound.","code":"invalid_request","param":"direction"}}
     * @response 400 scenario="Invalid since" {"error":{"type":"invalid_request_error","message":"Invalid since value.","code":"invalid_request","param":"since"}}
     * @response 400 scenario="Unknown cursor" {"error":{"type":"invalid_request_error","message":"Invalid starting_after cursor.","code":"invalid_request","param":"starting_after"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden domain" {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $user = $this->requireUser($request);
        $this->assertDomain($domain_uuid);

        $limit = max(1, min(500, (int) $request->input('limit', 100)));

        $direction = (string) $request->input('direction', '');
        $storedDirection = null;
        if ($direction !== '') {
            $storedDirection = array_search($direction, MessageApiPresenter::DIRECTION_MAP, true);
            if ($storedDirection === false) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid direction. Use inbound or outbound.', 'invalid_request', 'direction');
            }
        }

        $extensionUuid = (string) $request->input('extension_uuid', '');
        if ($extensionUuid !== '' && ! preg_match(self::UUID_PATTERN, $extensionUuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid extension UUID.', 'invalid_request', 'extension_uuid');
        }

        $startingAfter = (string) $request->input('starting_after', '');
        if ($startingAfter !== '' && ! preg_match(self::UUID_PATTERN, $startingAfter)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
        }

        $since = $this->parseTime($request, 'since');
        $until = $this->parseTime($request, 'until');

        $query = $this->scopedQuery($request, $user, $domain_uuid);

        if ($storedDirection !== null) {
            $query->where('direction', $storedDirection);
        }

        if ($extensionUuid !== '') {
            $query->where('extension_uuid', $extensionUuid);
        }

        $extension = trim((string) $request->input('extension', ''));
        if ($extension !== '') {
            $query->whereIn('extension_uuid', Extensions::query()
                ->select('extension_uuid')
                ->where('domain_uuid', $domain_uuid)
                ->where('extension', $extension));
        }

        $number = preg_replace('/\D+/', '', (string) $request->input('number', ''));
        if ($number !== '') {
            $pattern = '%' . $number;
            $query->where(function (Builder $q) use ($pattern) {
                $q->where(function (Builder $in) use ($pattern) {
                    $in->where('direction', 'in')->where('source', 'like', $pattern);
                })->orWhere(function (Builder $out) use ($pattern) {
                    $out->where('direction', 'out')->where('destination', 'like', $pattern);
                });
            });
        }

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        if ($until !== null) {
            $query->where('created_at', '<', $until);
        }

        if ($startingAfter !== '') {
            // The cursor is looked up inside the same domain scope, so a UUID
            // from another domain is indistinguishable from an unknown one.
            $anchor = $this->scopedQuery($request, $user, $domain_uuid)
                ->where('message_uuid', $startingAfter)
                ->first(['message_uuid', 'created_at']);

            if (! $anchor) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after cursor.', 'invalid_request', 'starting_after');
            }

            $anchorCreatedAt = $anchor->getRawOriginal('created_at');
            $query->where(function (Builder $q) use ($anchorCreatedAt, $startingAfter) {
                $q->where('created_at', '>', $anchorCreatedAt)
                    ->orWhere(function (Builder $same) use ($anchorCreatedAt, $startingAfter) {
                        $same->where('created_at', $anchorCreatedAt)
                            ->where('message_uuid', '>', $startingAfter);
                    });
            });
        }

        $rows = $query
            ->orderBy('created_at')
            ->orderBy('message_uuid')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $extensionNumbers = $this->extensionNumbers($domain_uuid, $rows->pluck('extension_uuid')->filter()->unique()->all());

        $data = $rows->map(fn (Messages $message) => $this->presenter->present(
            $message,
            $extensionNumbers[$message->extension_uuid] ?? null
        ))->values()->all();

        return response()->json([
            'object' => 'list',
            'url' => "/api/v1/domains/{$domain_uuid}/messages",
            'has_more' => $hasMore,
            'data' => $data,
        ], 200);
    }

    /**
     * Retrieve a message
     *
     * Returns one SMS/MMS message in the specified domain, in the same shape
     * as a list item.
     *
     * @group Messages
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam message_uuid string required The message UUID. Example: 2b1f0f51-7b0e-4b55-9a39-0c1c5f0b7e11
     *
     * @response 400 scenario="Invalid message UUID" {"error":{"type":"invalid_request_error","message":"Invalid message UUID.","code":"invalid_request","param":"message_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Message not found" {"error":{"type":"invalid_request_error","message":"Message not found.","code":"resource_missing","param":"message_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $message_uuid)
    {
        $message = $this->findMessage($request, $domain_uuid, $message_uuid);

        $extensionNumbers = $this->extensionNumbers($domain_uuid, array_filter([$message->extension_uuid]));

        return response()->json(
            $this->presenter->present($message, $extensionNumbers[$message->extension_uuid] ?? null),
            200
        );
    }

    /**
     * Download message media
     *
     * Streams one MMS attachment. The URL is the `media[].url` of a message;
     * it needs the same Bearer token and domain access as the list.
     *
     * @group Messages
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam message_uuid string required The message UUID. Example: 2b1f0f51-7b0e-4b55-9a39-0c1c5f0b7e11
     * @urlParam index integer required Zero-based attachment index. Example: 0
     *
     * @response 404 scenario="Media not found" {"error":{"type":"invalid_request_error","message":"Media not found.","code":"resource_missing","param":"index"}}
     */
    public function media(
        Request $request,
        string $domain_uuid,
        string $message_uuid,
        string $index,
        MessageMediaObjectStorageService $mediaStorage
    ) {
        $message = $this->findMessage($request, $domain_uuid, $message_uuid);

        $media = is_array($message->media) ? array_values($message->media) : [];
        $position = ctype_digit($index) ? (int) $index : -1;
        $item = $media[$position] ?? null;

        if (! is_array($item) || empty($item['bucket']) || empty($item['object_key'])) {
            throw new ApiException(404, 'invalid_request_error', 'Media not found.', 'resource_missing', 'index');
        }

        try {
            $object = $mediaStorage->getObjectForDomain(
                domainUuid: (string) $message->domain_uuid,
                bucket: $item['bucket'],
                objectKey: $item['object_key']
            );
        } catch (Throwable $e) {
            logger()->warning('Message media fetch failed', [
                'message_uuid' => $message->message_uuid,
                'error' => $e->getMessage(),
            ]);

            throw new ApiException(404, 'invalid_request_error', 'Media not found.', 'resource_missing', 'index');
        }

        $fileName = (string) ($item['original_name'] ?? $item['stored_name'] ?? 'attachment');
        $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?: 'attachment';

        return new StreamedResponse(
            function () use ($object) {
                $stream = $object['body'];

                if (is_resource($stream)) {
                    fpassthru($stream);
                    return;
                }

                echo (string) $stream;
            },
            200,
            [
                'Content-Type' => $item['mime_type'] ?? $object['content_type'] ?? 'application/octet-stream',
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $fileName,
                    $fallbackName
                ),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function findMessage(Request $request, string $domain_uuid, string $message_uuid): Messages
    {
        $user = $this->requireUser($request);
        $this->assertDomain($domain_uuid);

        if (! preg_match(self::UUID_PATTERN, $message_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid message UUID.', 'invalid_request', 'message_uuid');
        }

        $message = $this->scopedQuery($request, $user, $domain_uuid)
            ->where('message_uuid', $message_uuid)
            ->first();

        if (! $message) {
            throw new ApiException(404, 'invalid_request_error', 'Message not found.', 'resource_missing', 'message_uuid');
        }

        return $message;
    }

    /**
     * Every read goes through this query: it pins the domain, keeps to
     * carrier SMS/MMS, and applies the own-extension rule.
     */
    private function scopedQuery(Request $request, $user, string $domain_uuid): Builder
    {
        $query = Messages::query()
            ->where('domain_uuid', $domain_uuid)
            ->whereIn('type', self::MESSAGE_TYPES);

        if (! $this->authz->userHasPermission($user, 'messages_view_as', $domain_uuid)) {
            $ownExtension = (string) ($user->extension_uuid ?? '');

            if ($ownExtension === '') {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('extension_uuid', $ownExtension);
            }
        }

        return $query;
    }

    private function requireUser(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        return $user;
    }

    private function assertDomain(string $domain_uuid): void
    {
        if (! preg_match(self::UUID_PATTERN, $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! Domain::query()->where('domain_uuid', $domain_uuid)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }
    }

    private function parseTime(Request $request, string $param): ?string
    {
        if (! $request->filled($param)) {
            return null;
        }

        $value = trim((string) $request->input($param));

        try {
            $time = ctype_digit($value)
                ? Carbon::createFromTimestampUTC((int) $value)
                : Carbon::parse($value)->utc();
        } catch (Throwable) {
            throw new ApiException(400, 'invalid_request_error', "Invalid {$param} value.", 'invalid_request', $param);
        }

        return $time->format('Y-m-d H:i:s');
    }

    private function extensionNumbers(string $domain_uuid, array $extensionUuids): array
    {
        if ($extensionUuids === []) {
            return [];
        }

        return Extensions::query()
            ->where('domain_uuid', $domain_uuid)
            ->whereIn('extension_uuid', array_values($extensionUuids))
            ->pluck('extension', 'extension_uuid')
            ->all();
    }
}
