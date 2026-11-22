<?php

namespace Tests\Unit;

use App\Services\RingGroupService;
use PHPUnit\Framework\TestCase;

class RingGroupCallTimeoutTest extends TestCase
{
    public function test_partial_patch_does_not_touch_call_timeout(): void
    {
        $data = (new RingGroupService())->buildUpdateData(
            ['ring_group_name' => 'Sales'],
            'example.com',
            ['strategy' => 'simultaneous', 'members' => null]
        );

        $this->assertArrayNotHasKey('ring_group_call_timeout', $data);
    }

    public function test_patch_with_members_recalculates_timeout(): void
    {
        $data = (new RingGroupService())->buildUpdateData(
            ['members' => [['enabled' => true, 'delay' => 5, 'timeout' => 20]]],
            'example.com',
            ['strategy' => 'simultaneous', 'members' => null]
        );

        $this->assertSame(25, $data['ring_group_call_timeout']);
    }

    public function test_strategy_change_without_members_uses_stored_members(): void
    {
        $stored = [
            ['enabled' => true, 'timeout' => 10, 'delay' => 0],
            ['enabled' => true, 'timeout' => 15, 'delay' => 0],
        ];

        $data = (new RingGroupService())->buildUpdateData(
            ['ring_group_strategy' => 'sequence'],
            'example.com',
            ['strategy' => 'simultaneous', 'members' => $stored]
        );

        $this->assertSame(25, $data['ring_group_call_timeout']);
    }

    public function test_create_without_existing_state_still_calculates(): void
    {
        $data = (new RingGroupService())->buildUpdateData(['ring_group_strategy' => 'simultaneous'], 'example.com');

        $this->assertSame(0, $data['ring_group_call_timeout']);
    }

    public function test_api_member_shape_is_recalculated_for_each_strategy(): void
    {
        $members = [
            ['destination_enabled' => true, 'destination_delay' => 0, 'destination_timeout' => 20],
            ['destination_enabled' => true, 'destination_delay' => 5, 'destination_timeout' => 30],
            ['destination_enabled' => false, 'destination_delay' => 0, 'destination_timeout' => 99],
        ];
        $expected = ['simultaneous' => 35, 'sequence' => 50, 'rollover' => 30];

        foreach ($expected as $strategy => $seconds) {
            $data = (new RingGroupService())->buildUpdateData(
                ['ring_group_strategy' => $strategy, 'members' => $members],
                'example.com',
                ['strategy' => 'simultaneous', 'members' => null]
            );
            $this->assertSame($seconds, $data['ring_group_call_timeout'], $strategy);
        }
    }
}
