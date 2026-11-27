<?php

namespace Tests\Unit;

use Tests\TestCase;

class FanvilSideScreenKeyMappingTest extends TestCase
{
    private function map(string $vendor, string $area, bool $split): array
    {
        return fspbx_map_simple_key_to_fusion_row(
            ['key_area' => $area, 'key_index' => 1, 'key_type' => 'blf', 'key_value' => '4003', 'key_label' => 'Front Office'],
            'device-uuid',
            $vendor,
            [],
            '',
            [],
            $split
        );
    }

    public function test_fanvil_function_keys_go_to_side_screens_when_side_rows_exist(): void
    {
        $this->assertSame('memory', $this->map('fanvil', 'main', true)['device_key_category']);
        $this->assertSame('line', $this->map('fanvil', 'side', true)['device_key_category']);
    }

    public function test_fanvil_main_keys_stay_on_line_without_side_rows(): void
    {
        $this->assertSame('line', $this->map('fanvil', 'main', false)['device_key_category']);
    }

    public function test_other_vendors_are_not_split(): void
    {
        $this->assertSame('line', $this->map('yealink', 'main', true)['device_key_category']);
    }

    public function test_key_rows_have_area_detects_side_rows(): void
    {
        $this->assertTrue(fspbx_key_rows_have_area([['key_area' => 'main'], ['key_area' => 'side']], 'side'));
        $this->assertFalse(fspbx_key_rows_have_area([['key_area' => 'main'], []], 'side'));
    }
}
