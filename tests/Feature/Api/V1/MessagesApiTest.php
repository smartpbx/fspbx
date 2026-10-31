<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\MessageController;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Support\MessagingSqliteSchema;
use Tests\TestCase;

class MessagesApiTest extends TestCase
{
    use MessagingSqliteSchema;

    private const DOMAIN_A = '11111111-1111-4111-8111-111111111111';
    private const DOMAIN_B = '22222222-2222-4222-8222-222222222222';
    private const EXT_A1 = 'aaaaaaaa-0000-4000-8000-000000000001';
    private const EXT_A2 = 'aaaaaaaa-0000-4000-8000-000000000002';
    private const EXT_B1 = 'bbbbbbbb-0000-4000-8000-000000000001';

    private const MSG_A_IN = 'a0000000-0000-4000-8000-000000000001';
    private const MSG_A_OUT = 'a0000000-0000-4000-8000-000000000002';
    private const MSG_A_EXT2 = 'a0000000-0000-4000-8000-000000000003';
    private const MSG_B_IN = 'b0000000-0000-4000-8000-000000000001';

    private array $grants = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMessagingSchema();

        $this->insertDomain(self::DOMAIN_A, 'a.example.com');
        $this->insertDomain(self::DOMAIN_B, 'b.example.com');
        $this->insertExtension(self::DOMAIN_A, self::EXT_A1, '1001');
        $this->insertExtension(self::DOMAIN_A, self::EXT_A2, '1002');
        $this->insertExtension(self::DOMAIN_B, self::EXT_B1, '1001');

        $this->insertMessage([
            'message_uuid' => self::MSG_A_IN,
            'domain_uuid' => self::DOMAIN_A,
            'extension_uuid' => self::EXT_A1,
            'direction' => 'in',
            'type' => 'mms',
            'source' => '+12025550111',
            'destination' => '+12025550100',
            'message' => 'inbound with a photo',
            'reference_id' => 'prov-in-1',
            'media' => [[
                'bucket' => 'media-bucket',
                'object_key' => 'a/photo.jpg',
                'original_name' => 'photo.jpg',
                'stored_name' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 1234,
            ]],
            'created_at' => '2026-10-01 12:00:00',
        ]);
        $this->insertMessage([
            'message_uuid' => self::MSG_A_OUT,
            'domain_uuid' => self::DOMAIN_A,
            'extension_uuid' => self::EXT_A1,
            'direction' => 'out',
            'source' => '+12025550100',
            'destination' => '+12025550111',
            'message' => 'outbound reply',
            'status' => 'success',
            'delivery_meta' => ['outbound' => ['provider' => ['name' => 'example']]],
            'created_at' => '2026-10-01 12:05:00',
        ]);
        $this->insertMessage([
            'message_uuid' => self::MSG_A_EXT2,
            'domain_uuid' => self::DOMAIN_A,
            'extension_uuid' => self::EXT_A2,
            'direction' => 'in',
            'source' => '+12025550122',
            'created_at' => '2026-10-02 09:00:00',
        ]);
        $this->insertMessage([
            'domain_uuid' => self::DOMAIN_A,
            'extension_uuid' => self::EXT_A1,
            'type' => 'chat',
            'created_at' => '2026-10-02 10:00:00',
        ]);
        $this->insertMessage([
            'message_uuid' => self::MSG_B_IN,
            'domain_uuid' => self::DOMAIN_B,
            'extension_uuid' => self::EXT_B1,
            'source' => '+12025550111',
            'message' => 'domain B secret',
            'created_at' => '2026-10-01 12:01:00',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson($this->listUrl(self::DOMAIN_A))->assertStatus(401);
    }

    public function test_routes_are_registered_with_messages_view_permission(): void
    {
        foreach ([
            ['/messages', 'index'],
            ['/messages/' . self::MSG_A_IN, 'show'],
            ['/messages/' . self::MSG_A_IN . '/media/0', 'media'],
        ] as [$suffix, $method]) {
            $route = app('router')->getRoutes()->match(
                Request::create('/api/v1/domains/' . self::DOMAIN_A . $suffix, 'GET')
            );

            $this->assertSame(MessageController::class . '@' . $method, $route->getActionName());
            $this->assertContains('user.authorize:messages_view', $route->gatherMiddleware());
            $this->assertContains('api.token.auth', $route->gatherMiddleware());
        }
    }

    public function test_lists_only_the_requested_domain_with_the_documented_shape(): void
    {
        $this->actingAsDomainAUser();

        $response = $this->getJson($this->listUrl(self::DOMAIN_A))->assertOk();

        $response->assertJsonPath('object', 'list')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('url', '/api/v1/domains/' . self::DOMAIN_A . '/messages');

        $this->assertSame(
            [self::MSG_A_IN, self::MSG_A_OUT, self::MSG_A_EXT2],
            array_column($response->json('data'), 'message_uuid'),
            'oldest first, domain A only, non-SMS/MMS rows excluded'
        );
        $this->assertNotContains(self::DOMAIN_B, array_column($response->json('data'), 'domain_uuid'));

        $first = $response->json('data.0');
        $this->assertSame([
            'object', 'message_uuid', 'domain_uuid', 'direction', 'type', 'from', 'to',
            'extension_uuid', 'extension', 'body', 'media', 'status', 'provider',
            'provider_message_id', 'created_at', 'updated_at',
        ], array_keys($first));
        $this->assertSame('inbound', $first['direction']);
        $this->assertSame('mms', $first['type']);
        $this->assertSame('+12025550111', $first['from']);
        $this->assertSame('1001', $first['extension']);
        $this->assertSame('prov-in-1', $first['provider_message_id']);
        $this->assertSame('example', $first['provider']);
        $this->assertSame('2026-10-01T12:00:00Z', $first['created_at']);
        $this->assertSame('image/jpeg', $first['media'][0]['content_type']);
        $this->assertStringEndsWith(
            '/api/v1/domains/' . self::DOMAIN_A . '/messages/' . self::MSG_A_IN . '/media/0',
            $first['media'][0]['url']
        );
        $this->assertArrayNotHasKey('bucket', $first['media'][0]);
        $this->assertArrayNotHasKey('object_key', $first['media'][0]);

        $this->assertSame('outbound', $response->json('data.1.direction'));
    }

    public function test_domain_a_token_cannot_list_domain_b(): void
    {
        $this->actingAsDomainAUser();

        $this->getJson($this->listUrl(self::DOMAIN_B))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden_domain');
    }

    public function test_domain_a_token_cannot_read_a_domain_b_message_through_its_own_domain(): void
    {
        $this->actingAsDomainAUser();

        $this->getJson($this->listUrl(self::DOMAIN_A) . '/' . self::MSG_B_IN)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'resource_missing');

        $this->getJson($this->listUrl(self::DOMAIN_A) . '/' . self::MSG_B_IN . '/media/0')
            ->assertStatus(404);

        $this->getJson($this->listUrl(self::DOMAIN_A) . '?starting_after=' . self::MSG_B_IN)
            ->assertStatus(400)
            ->assertJsonPath('error.param', 'starting_after');
    }

    public function test_show_returns_a_single_message(): void
    {
        $this->actingAsDomainAUser();

        $this->getJson($this->listUrl(self::DOMAIN_A) . '/' . self::MSG_A_OUT)
            ->assertOk()
            ->assertJsonPath('object', 'message')
            ->assertJsonPath('message_uuid', self::MSG_A_OUT)
            ->assertJsonPath('direction', 'outbound')
            ->assertJsonPath('to', '+12025550111');
    }

    public function test_media_streams_behind_the_same_auth(): void
    {
        $this->actingAsDomainAUser();

        $storage = Mockery::mock(\App\Services\MessageMediaObjectStorageService::class);
        $storage->shouldReceive('getObjectForDomain')
            ->once()
            ->with(self::DOMAIN_A, 'media-bucket', 'a/photo.jpg')
            ->andReturn(['body' => 'jpeg-bytes', 'content_type' => 'image/jpeg', 'content_length' => 10]);
        $this->app->instance(\App\Services\MessageMediaObjectStorageService::class, $storage);

        $response = $this->get($this->listUrl(self::DOMAIN_A) . '/' . self::MSG_A_IN . '/media/0');
        $response->assertOk();
        $this->assertSame('jpeg-bytes', $response->streamedContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));

        $this->getJson($this->listUrl(self::DOMAIN_A) . '/' . self::MSG_A_IN . '/media/5')
            ->assertStatus(404)
            ->assertJsonPath('error.param', 'index');
    }

    public function test_direction_filter(): void
    {
        $this->actingAsDomainAUser();

        $this->assertListed([self::MSG_A_OUT], '?direction=outbound');
        $this->assertListed([self::MSG_A_IN, self::MSG_A_EXT2], '?direction=inbound');

        $this->getJson($this->listUrl(self::DOMAIN_A) . '?direction=sideways')
            ->assertStatus(400)
            ->assertJsonPath('error.param', 'direction');
    }

    public function test_extension_filters(): void
    {
        $this->actingAsDomainAUser();

        $this->assertListed([self::MSG_A_EXT2], '?extension=1002');
        $this->assertListed([self::MSG_A_IN, self::MSG_A_OUT], '?extension_uuid=' . self::EXT_A1);
    }

    public function test_since_and_until_filters_accept_iso_and_epoch(): void
    {
        $this->actingAsDomainAUser();

        $this->assertListed([self::MSG_A_OUT, self::MSG_A_EXT2], '?since=2026-10-01T12:05:00Z');
        $this->assertListed([self::MSG_A_EXT2], '?since=' . strtotime('2026-10-02 00:00:00 UTC'));
        $this->assertListed([self::MSG_A_IN], '?until=2026-10-01T12:05:00Z');

        $this->getJson($this->listUrl(self::DOMAIN_A) . '?since=not-a-time')
            ->assertStatus(400)
            ->assertJsonPath('error.param', 'since');
    }

    public function test_number_filter_matches_the_external_party(): void
    {
        $this->actingAsDomainAUser();

        $this->assertListed([self::MSG_A_IN, self::MSG_A_OUT], '?number=(202) 555-0111');
        $this->assertListed([self::MSG_A_EXT2], '?number=2025550122');
    }

    public function test_cursor_pagination(): void
    {
        $this->actingAsDomainAUser();

        $page1 = $this->getJson($this->listUrl(self::DOMAIN_A) . '?limit=2')->assertOk();
        $page1->assertJsonPath('has_more', true);
        $this->assertSame([self::MSG_A_IN, self::MSG_A_OUT], array_column($page1->json('data'), 'message_uuid'));

        $page2 = $this->getJson($this->listUrl(self::DOMAIN_A) . '?limit=2&starting_after=' . self::MSG_A_OUT)->assertOk();
        $page2->assertJsonPath('has_more', false);
        $this->assertSame([self::MSG_A_EXT2], array_column($page2->json('data'), 'message_uuid'));
    }

    public function test_without_view_as_only_the_users_own_extension_is_visible(): void
    {
        $this->actingAsDomainAUser(viewAs: false, extensionUuid: self::EXT_A2);

        $this->assertListed([self::MSG_A_EXT2], '');
        $this->getJson($this->listUrl(self::DOMAIN_A) . '/' . self::MSG_A_IN)->assertStatus(404);
    }

    public function test_without_view_as_or_extension_nothing_is_visible(): void
    {
        $this->actingAsDomainAUser(viewAs: false, extensionUuid: null);

        $this->assertListed([], '');
    }

    private function assertListed(array $expected, string $query): void
    {
        $response = $this->getJson($this->listUrl(self::DOMAIN_A) . $query)->assertOk();

        $this->assertSame($expected, array_column($response->json('data'), 'message_uuid'), "query: {$query}");
    }

    private function listUrl(string $domainUuid): string
    {
        return "/api/v1/domains/{$domainUuid}/messages";
    }

    /**
     * A token holder whose only domain is DOMAIN_A.
     */
    private function actingAsDomainAUser(bool $viewAs = true, ?string $extensionUuid = self::EXT_A1): void
    {
        $user = new User();
        $user->forceFill([
            'user_uuid' => 'cccccccc-0000-4000-8000-000000000001',
            'domain_uuid' => self::DOMAIN_A,
            'extension_uuid' => $extensionUuid,
        ]);

        $authz = Mockery::mock(PermissionService::class);
        $authz->shouldReceive('userCanAccessDomain')
            ->andReturnUsing(fn ($u, string $domain) => $domain === self::DOMAIN_A);
        $authz->shouldReceive('userHasPermission')
            ->andReturnUsing(function ($u, string $permission, ?string $domain = null) use ($viewAs) {
                if ($domain !== null && $domain !== self::DOMAIN_A) {
                    return false;
                }

                return match ($permission) {
                    'messages_view' => true,
                    'messages_view_as' => $viewAs,
                    default => false,
                };
            });
        $this->app->instance(PermissionService::class, $authz);

        Sanctum::actingAs($user, ['*']);
        $this->withToken('test-token');
    }
}
