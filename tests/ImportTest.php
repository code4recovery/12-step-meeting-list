<?php

/**
 * Tests for the CSV / feed import normalization in includes/functions_import.php.
 * These cover the pure transformation steps (no network / geocoding).
 */
class ImportTest extends WP_UnitTestCase
{
    public function test_reformat_googlesheet_maps_headers_to_keys()
    {
        $data = [
            'values' => [
                ['Name', 'Day', 'Conference URL'],
                ['Monday Group', 'Monday', 'https://zoom.us/j/1'],
            ],
        ];

        $result = tsml_import_reformat_googlesheet($data);

        $this->assertSame([
            [
                'name'           => 'Monday Group',
                'day'            => 'Monday',
                'conference_url' => 'https://zoom.us/j/1',
            ],
        ], $result);
    }

    public function test_sanitize_meetings_normalizes_day_and_time()
    {
        $meetings = tsml_import_sanitize_meetings([
            ['name' => 'Monday Group', 'day' => 'Monday', 'time' => '7:00 PM', 'address' => '123 Main St'],
        ]);

        $this->assertCount(1, $meetings);
        $meeting = array_values($meetings)[0];
        $this->assertSame(1, $meeting['day']);
        $this->assertSame('19:00', $meeting['time']);
        $this->assertSame('Monday Group', $meeting['name']);
        $this->assertSame('monday-group', $meeting['slug']);
        $this->assertSame('123 Main St', $meeting['formatted_address']);
        $this->assertArrayNotHasKey('address', $meeting);
    }

    public function test_sanitize_meetings_without_data_source_url_has_no_deprecations()
    {
        // a CSV upload has no data source url
        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            $deprecations[] = $errstr;
            return true;
        }, E_DEPRECATED);

        tsml_import_sanitize_meetings([
            ['name' => 'Monday Group', 'day' => 'Monday', 'time' => '7:00 PM', 'address' => '123 Main St'],
        ]);

        restore_error_handler();
        $this->assertSame([], $deprecations);
    }

    public function test_sanitize_meetings_without_time_is_by_appointment()
    {
        $meetings = tsml_import_sanitize_meetings([
            ['name' => 'Big Book Study', 'address' => '456 Oak Ave'],
        ]);

        $meeting = array_values($meetings)[0];
        $this->assertFalse($meeting['day']);
        $this->assertFalse($meeting['time']);
        $this->assertSame('Big Book Study', $meeting['name']);
    }

    public function test_sanitize_meetings_translates_type_names_and_prefers_closed()
    {
        $meetings = tsml_import_sanitize_meetings([
            [
                'name'    => 'Typed Group',
                'day'     => 'Tuesday',
                'time'    => '6:00 PM',
                'address' => '789 Elm St',
                'types'   => 'Closed, Discussion, Open',
            ],
        ]);

        $meeting = array_values($meetings)[0];
        $types   = $meeting['types'];
        sort($types);

        // "Open" is dropped because the meeting is also "Closed".
        $this->assertSame(['C', 'D'], $types);
    }

    public function test_sanitize_meetings_drops_rows_without_identifiers()
    {
        $meetings = tsml_import_sanitize_meetings([
            ['time' => '7:00 PM', 'day' => 'Monday'],
        ]);

        $this->assertCount(0, $meetings);
    }

    public function test_remove_change_detection_clears_scans_and_setting()
    {
        $feed = 'https://district.example.org/wp-admin/admin-ajax.php?action=meetings';
        $removed_feed = 'https://removed.example.org/wp-admin/admin-ajax.php?action=meetings';
        $other_feed = 'https://other.example.org/wp-admin/admin-ajax.php?action=meetings';
        update_option('tsml_data_sources', [
            $feed => ['name' => 'District', 'count_meetings' => 6, 'change_detect' => 'enabled', 'type' => 'JSON'],
            $other_feed => ['name' => 'Other', 'count_meetings' => 3, 'type' => 'JSON'],
        ]);
        wp_schedule_event(time(), 'daily', 'tsml_scan_data_source', [$feed]);
        wp_schedule_event(time(), 'daily', 'tsml_scan_data_source', [$removed_feed]);
        wp_schedule_event(time(), 'hourly', 'tsml_auto_import');

        tsml_import_remove_change_detection();

        $this->assertFalse(wp_next_scheduled('tsml_scan_data_source', [$feed]));
        $this->assertFalse(wp_next_scheduled('tsml_scan_data_source', [$removed_feed]));
        $this->assertNotFalse(wp_next_scheduled('tsml_auto_import'));
        $this->assertSame([
            $feed => ['name' => 'District', 'count_meetings' => 6, 'type' => 'JSON'],
            $other_feed => ['name' => 'Other', 'count_meetings' => 3, 'type' => 'JSON'],
        ], get_option('tsml_data_sources'));
    }

    public function test_remove_change_detection_without_data_sources_adds_no_option()
    {
        delete_option('tsml_data_sources');

        tsml_import_remove_change_detection();

        $this->assertFalse(get_option('tsml_data_sources'));
    }
}
