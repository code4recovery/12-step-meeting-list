<?php

/**
 * Tests for the region hooks in includes/admin_region.php: editing or deleting a region
 * keeps the cached meetings feed (and its regions array) in step with the region hierarchy.
 */
class RegionsTest extends WP_UnitTestCase
{
    private $cache_writable;

    public function set_up()
    {
        global $tsml_cache_writable;
        parent::set_up();

        // the region hooks check this permission
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        // read meetings from the cache file, as the public feed does
        $this->cache_writable = $tsml_cache_writable;
        $tsml_cache_writable = true;
    }

    public function tear_down()
    {
        global $tsml_cache, $tsml_cache_writable;
        $tsml_cache_writable = $this->cache_writable;
        if (file_exists(WP_CONTENT_DIR . $tsml_cache)) {
            unlink(WP_CONTENT_DIR . $tsml_cache);
        }
        parent::tear_down();
    }

    private function create_region($name, $parent = 0)
    {
        return self::factory()->term->create(['taxonomy' => 'tsml_region', 'name' => $name, 'parent' => $parent]);
    }

    private function create_meeting($region_id)
    {
        $location_id = self::factory()->post->create([
            'post_type'   => 'tsml_location',
            'post_title'  => 'Community Center',
            'post_status' => 'publish',
        ]);
        update_post_meta($location_id, 'formatted_address', '573 Castro St, San Francisco, CA 94114, USA');
        update_post_meta($location_id, 'latitude', '37.7609');
        update_post_meta($location_id, 'longitude', '-122.4350');
        wp_set_object_terms($location_id, $region_id, 'tsml_region');

        $meeting_id = self::factory()->post->create([
            'post_type'   => 'tsml_meeting',
            'post_title'  => 'Monday Night Group',
            'post_status' => 'publish',
            'post_parent' => $location_id,
        ]);
        update_post_meta($meeting_id, 'day', 1);
        update_post_meta($meeting_id, 'time', '19:00');

        return $meeting_id;
    }

    // build the cache file, then read the meeting back from it
    private function cached_meeting($meeting_id)
    {
        global $tsml_cache;
        $this->assertFileExists(WP_CONTENT_DIR . $tsml_cache, 'The meetings cache file should be written');
        foreach (tsml_get_meetings() as $meeting) {
            if ((int) $meeting['id'] === (int) $meeting_id) {
                return $meeting;
            }
        }
        $this->fail('Meeting should be in the cached feed');
    }

    private function set_modified($post_id, $date)
    {
        global $wpdb;
        $wpdb->update($wpdb->posts, ['post_modified' => $date, 'post_modified_gmt' => $date], ['ID' => $post_id]);
        clean_post_cache($post_id);
    }

    public function test_moving_a_region_updates_the_cached_regions()
    {
        // a flattened hierarchy, as after a CSV import
        $california = $this->create_region('California');
        $san_francisco = $this->create_region('San Francisco');
        $castro = $this->create_region('Castro', $san_francisco);
        $meeting_id = $this->create_meeting($castro);

        tsml_cache_rebuild();
        $this->assertSame(['San Francisco', 'Castro'], $this->cached_meeting($meeting_id)['regions']);

        wp_update_term($san_francisco, 'tsml_region', ['parent' => $california]);

        $this->assertSame(['California', 'San Francisco', 'Castro'], $this->cached_meeting($meeting_id)['regions']);
    }

    public function test_deleting_a_region_updates_the_cached_regions()
    {
        $california = $this->create_region('California');
        $san_francisco = $this->create_region('San Francisco', $california);
        $castro = $this->create_region('Castro', $san_francisco);
        $meeting_id = $this->create_meeting($castro);

        tsml_cache_rebuild();
        $this->assertSame(['California', 'San Francisco', 'Castro'], $this->cached_meeting($meeting_id)['regions']);

        // Castro moves up to California
        wp_delete_term($san_francisco, 'tsml_region');

        $this->assertSame(['California', 'Castro'], $this->cached_meeting($meeting_id)['regions']);
    }

    public function test_editing_a_region_only_touches_its_own_meetings()
    {
        $california = $this->create_region('California');
        $san_francisco = $this->create_region('San Francisco', $california);
        $texas = $this->create_region('Texas');
        $san_francisco_meeting = $this->create_meeting($san_francisco);
        $texas_meeting = $this->create_meeting($texas);

        tsml_cache_rebuild();
        $old_date = '2020-01-01 00:00:00';
        $this->set_modified($san_francisco_meeting, $old_date);
        $this->set_modified($texas_meeting, $old_date);

        wp_update_term($california, 'tsml_region', ['description' => 'The Golden State']);

        $this->assertNotSame($old_date, get_post($san_francisco_meeting)->post_modified, 'A meeting in a sub-region should be marked as updated');
        $this->assertSame($old_date, get_post($texas_meeting)->post_modified, 'A meeting in another region should not be touched');
    }
}
