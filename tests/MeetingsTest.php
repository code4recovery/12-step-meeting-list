<?php

/**
 * Integration tests: create real location + meeting posts and read them back
 * through tsml_get_meetings().
 */
class MeetingsTest extends WP_UnitTestCase
{
    private function create_meeting()
    {
        $location_id = self::factory()->post->create([
            'post_type'   => 'tsml_location',
            'post_title'  => 'Community Center',
            'post_status' => 'publish',
        ]);
        update_post_meta($location_id, 'formatted_address', '123 Main St, Chicago, IL 60601, USA');
        update_post_meta($location_id, 'latitude', '41.8781');
        update_post_meta($location_id, 'longitude', '-87.6298');
        update_post_meta($location_id, 'timezone', 'America/Chicago');

        $meeting_id = self::factory()->post->create([
            'post_type'   => 'tsml_meeting',
            'post_title'  => 'Monday Night Group',
            'post_status' => 'publish',
            'post_parent' => $location_id,
        ]);
        update_post_meta($meeting_id, 'day', 1);
        update_post_meta($meeting_id, 'time', '19:00');
        update_post_meta($meeting_id, 'types', ['O', 'D']);

        return [$location_id, $meeting_id];
    }

    private function find_meeting($meetings, $meeting_id)
    {
        foreach ($meetings as $meeting) {
            if ((int) $meeting['id'] === (int) $meeting_id) {
                return $meeting;
            }
        }
        return null;
    }

    public function test_get_meetings_returns_created_meeting_with_location()
    {
        list($location_id, $meeting_id) = $this->create_meeting();

        $meetings = tsml_get_meetings([], false);
        $meeting  = $this->find_meeting($meetings, $meeting_id);

        $this->assertNotNull($meeting, 'Created meeting should be returned by tsml_get_meetings()');
        $this->assertSame('Monday Night Group', $meeting['name']);
        $this->assertSame('1', (string) $meeting['day']);
        $this->assertSame('19:00', $meeting['time']);
        $this->assertSame('Community Center', $meeting['location']);
        $this->assertSame($location_id, $meeting['location_id']);
        $this->assertContains('O', $meeting['types']);
        $this->assertContains('D', $meeting['types']);
    }

    public function test_get_meetings_skips_meeting_without_location()
    {
        $meeting_id = self::factory()->post->create([
            'post_type'   => 'tsml_meeting',
            'post_title'  => 'Orphan Meeting',
            'post_status' => 'publish',
            'post_parent' => 0,
        ]);

        $meetings = tsml_get_meetings([], false);

        $this->assertNull($this->find_meeting($meetings, $meeting_id));
    }

    public function test_feed_does_not_default_entity_email_to_site_admin()
    {
        delete_option('tsml_entity');
        list(, $meeting_id) = $this->create_meeting();

        $meeting = $this->find_meeting(tsml_get_meetings([], false), $meeting_id);

        $this->assertArrayNotHasKey('entity_email', $meeting);
        $this->assertArrayNotHasKey('entity_email', tsml_get_entity());
    }

    public function test_feed_includes_saved_entity_email()
    {
        update_option('tsml_entity', ['entity_email' => 'intergroup@example.org']);
        list(, $meeting_id) = $this->create_meeting();

        $meeting = $this->find_meeting(tsml_get_meetings([], false), $meeting_id);

        $this->assertSame('intergroup@example.org', $meeting['entity_email']);
    }

    public function test_count_meetings_reflects_published_posts()
    {
        $before = tsml_count_meetings();
        $this->create_meeting();
        $this->assertSame($before + 1, tsml_count_meetings());
    }
}
