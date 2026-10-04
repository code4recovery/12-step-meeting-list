<?php

/**
 * Tests for manually placed map pins (the use_custom_coordinates location flag):
 * validation, the flag helper, imports keeping the pin, and the tsml_address lookup.
 */
class CustomCoordinatesTest extends WP_Ajax_UnitTestCase
{
    const ADDRESS = '82 Firmount Road, Goede Hoop, Cape Town, 7130, South Africa';

    public function set_up()
    {
        parent::set_up();

        // geocode from the overrides list so no network request is made
        tsml_custom_addresses([
            self::ADDRESS => [
                'formatted_address' => self::ADDRESS,
                'city' => 'Cape Town',
                'latitude' => -34.065333,
                'longitude' => 18.836000,
                'approximate' => 'no',
            ],
        ]);
    }

    private function create_location($use_custom_coordinates)
    {
        $location_id = self::factory()->post->create([
            'post_type'   => 'tsml_location',
            'post_title'  => 'Community Hall',
            'post_status' => 'publish',
        ]);
        update_post_meta($location_id, 'formatted_address', self::ADDRESS);
        update_post_meta($location_id, 'latitude', '-34.0652985');
        update_post_meta($location_id, 'longitude', '18.8359404');
        update_post_meta($location_id, 'approximate', 'no');
        wp_set_object_terms($location_id, self::factory()->term->create(['taxonomy' => 'tsml_region', 'name' => 'Cape Town']), 'tsml_region');
        tsml_update_use_custom_coordinates($location_id, $use_custom_coordinates);
        return $location_id;
    }

    private function import_meeting_at_address()
    {
        tsml_import_buffer_set(tsml_import_sanitize_meetings([
            ['name' => 'Monday Group', 'day' => 'Monday', 'time' => '7:00 PM', 'location' => 'Community Hall', 'address' => self::ADDRESS],
        ]));
        tsml_import_buffer_next(25);
    }

    public function test_coordinates_valid()
    {
        $this->assertTrue(tsml_coordinates_valid('-34.0652985', '18.8359404'));
        $this->assertTrue(tsml_coordinates_valid(90, -180));
        $this->assertFalse(tsml_coordinates_valid('90.1', '0'));
        $this->assertFalse(tsml_coordinates_valid('0', '180.1'));
        $this->assertFalse(tsml_coordinates_valid('', '18.8'));
        $this->assertFalse(tsml_coordinates_valid('north', '18.8'));
    }

    public function test_update_use_custom_coordinates_sets_and_clears_flag()
    {
        $location_id = $this->create_location(true);
        $this->assertSame('yes', get_post_meta($location_id, 'use_custom_coordinates', true));

        tsml_update_use_custom_coordinates($location_id, false);
        $this->assertFalse(metadata_exists('post', $location_id, 'use_custom_coordinates'));
    }

    public function test_import_keeps_manually_placed_pin()
    {
        $location_id = $this->create_location(true);

        $this->import_meeting_at_address();

        $this->assertSame('-34.0652985', get_post_meta($location_id, 'latitude', true));
        $this->assertSame('18.8359404', get_post_meta($location_id, 'longitude', true));
        $this->assertSame('yes', get_post_meta($location_id, 'use_custom_coordinates', true));
    }

    public function test_import_replaces_geocoded_pin()
    {
        $location_id = $this->create_location(false);

        $this->import_meeting_at_address();

        $this->assertEquals(-34.065333, get_post_meta($location_id, 'latitude', true));
        $this->assertEquals(18.836000, get_post_meta($location_id, 'longitude', true));
    }

    public function test_address_lookup_returns_manually_placed_pin()
    {
        $this->create_location(true);

        $response = $this->address_lookup();

        $this->assertSame('yes', $response['use_custom_coordinates']);
        $this->assertSame('-34.0652985', $response['latitude']);
        $this->assertSame('18.8359404', $response['longitude']);
    }

    public function test_address_lookup_omits_geocoded_pin()
    {
        $this->create_location(false);

        $response = $this->address_lookup();

        $this->assertSame('Community Hall', $response['location']);
        $this->assertArrayNotHasKey('use_custom_coordinates', $response);
    }

    private function address_lookup()
    {
        $this->_setRole('administrator');
        $_GET['formatted_address'] = self::ADDRESS;
        try {
            $this->_handleAjax('tsml_address');
        } catch (WPAjaxDieContinueException $e) {
            // wp_send_json() ends the request
        }
        return json_decode($this->_last_response, true);
    }
}
