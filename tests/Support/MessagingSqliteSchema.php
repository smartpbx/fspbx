<?php

namespace Tests\Support;

use App\Models\Messages;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A throwaway in-memory SQLite schema with just the tables the Messages API
 * and message webhooks touch, so these tests run without a PostgreSQL
 * install. Column names mirror the real migrations; only what the code
 * under test reads is created.
 */
trait MessagingSqliteSchema
{
    protected function setUpMessagingSchema(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'messaging.webhook_debug' => false,
            'broadcasting.default' => 'null',
        ]);
        $this->app->forgetInstance('encrypter');
        DB::purge('sqlite');

        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->string('domain_name')->nullable();
            $table->string('domain_enabled')->nullable();
            $table->string('domain_description')->nullable();
        });

        Schema::create('v_extensions', function (Blueprint $table) {
            $table->uuid('extension_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('extension');
        });

        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->uuid('domain_setting_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->uuid('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('message_uuid')->primary();
            $table->uuid('extension_uuid')->nullable();
            $table->uuid('domain_uuid')->nullable();
            $table->string('source');
            $table->string('destination');
            $table->text('message')->nullable();
            $table->string('direction');
            $table->string('type');
            $table->string('status')->nullable();
            $table->string('reference_id')->nullable();
            $table->json('media')->nullable();
            $table->json('delivery_meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('call_webhook_subscriptions', function (Blueprint $table) {
            $table->uuid('call_webhook_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('endpoint_url', 2048);
            $table->text('signing_secret');
            $table->boolean('enabled')->default(true);
            $table->json('events');
            $table->timestamps();
        });
    }

    protected function insertDomain(string $domainUuid, string $name): void
    {
        DB::table('v_domains')->insert([
            'domain_uuid' => $domainUuid,
            'domain_name' => $name,
            'domain_enabled' => 'true',
        ]);
    }

    protected function insertExtension(string $domainUuid, string $extensionUuid, string $extension): void
    {
        DB::table('v_extensions')->insert([
            'extension_uuid' => $extensionUuid,
            'domain_uuid' => $domainUuid,
            'extension' => $extension,
        ]);
    }

    protected function insertMessage(array $attributes): Messages
    {
        $message = new Messages();
        $message->forceFill(array_merge([
            'message_uuid' => (string) Str::uuid(),
            'source' => '+12025550101',
            'destination' => '+12025550100',
            'message' => 'hello',
            'direction' => 'in',
            'type' => 'sms',
            'status' => 'received',
            'media' => [],
            'delivery_meta' => ['provider' => ['name' => 'example']],
            'created_at' => '2026-10-01 12:00:00',
            'updated_at' => '2026-10-01 12:00:00',
        ], $attributes));
        $message->timestamps = false;
        $message->save();

        return $message;
    }
}
