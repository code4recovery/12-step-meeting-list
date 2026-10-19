<?php

/**
 * Tests for the sanitizing / string / timezone helpers in
 * includes/functions.php and includes/functions_timezone.php.
 */
class HelpersTest extends WP_UnitTestCase
{
    public function test_sanitize_url_rejects_non_http_schemes()
    {
        $this->assertSame('https://example.com', tsml_sanitize('url', 'https://example.com'));
        $this->assertSame('', tsml_sanitize('url', 'javascript:alert(1)'));
    }

    public function test_sanitize_phone_keeps_only_dialable_characters()
    {
        $this->assertSame('3125551234', tsml_sanitize('phone', '(312) 555-1234'));
        $this->assertSame('+1800#123', tsml_sanitize('phone', '+1 (800) # 123'));
    }

    public function test_sanitize_time_and_date()
    {
        $this->assertSame('18:30', tsml_sanitize('time', '6:30 pm'));
        $this->assertSame('2024-01-05', tsml_sanitize('date', '2024-01-05'));
    }

    public function test_sanitize_text_strips_tags()
    {
        $this->assertSame('hi', tsml_sanitize('text', '<b>hi</b>'));
    }

    public function test_sanitize_text_area_trims_each_line()
    {
        $this->assertSame("a\nb", tsml_sanitize_text_area("  a \n b  \n"));
    }

    public function test_string_ends()
    {
        $this->assertTrue(tsml_string_ends('meetings.json', '.json'));
        $this->assertTrue(tsml_string_ends('anything', ''));
        $this->assertFalse(tsml_string_ends('abc', 'd'));
    }

    public function test_string_tokens_removes_quotes_and_punctuation()
    {
        $this->assertSame(['OBriens', 'Group'], tsml_string_tokens("O'Brien's Group!"));
    }

    public function test_to_css_classes()
    {
        $this->assertSame('type-m type-onl', tsml_to_css_classes(['M', 'ONL']));
        $this->assertSame('flag-x', tsml_to_css_classes(['X'], 'flag-'));
        $this->assertSame('', tsml_to_css_classes([]));
    }

    public function test_sanitize_data_sort()
    {
        $this->assertSame('hello-world', tsml_sanitize_data_sort('Hello World'));
        $this->assertSame('cafe-bar', tsml_sanitize_data_sort('Cafe / Bar'));
        $this->assertSame('a-b-c', tsml_sanitize_data_sort('  a - b - c  '));
    }

    public function test_calculate_attendance_option()
    {
        $this->assertSame('online', tsml_calculate_attendance_option(['TC', 'ONL'], 'no'));
        $this->assertSame('inactive', tsml_calculate_attendance_option(['TC'], 'no'));
        $this->assertSame('hybrid', tsml_calculate_attendance_option(['ONL'], 'no'));
        $this->assertSame('online', tsml_calculate_attendance_option(['ONL'], 'yes'));
        $this->assertSame('in_person', tsml_calculate_attendance_option([], 'no'));
        $this->assertSame('inactive', tsml_calculate_attendance_option([], 'yes'));
        $this->assertSame('in_person', tsml_calculate_attendance_option(null, 'no'));
    }

    public function test_conference_provider_matches_known_domains()
    {
        $this->assertSame('Zoom', tsml_conference_provider('https://us02web.zoom.us/j/12345'));
        $this->assertSame('Google Meet', tsml_conference_provider('https://meet.google.com/abc-defg-hij'));
        $this->assertFalse(tsml_conference_provider('https://example.com/room'));
    }

    public function test_conference_providers_list()
    {
        $providers = tsml_conference_providers();
        $this->assertIsArray($providers);
        $this->assertContains('Zoom', $providers);
    }

    public function test_timezone_is_valid()
    {
        $this->assertTrue(tsml_timezone_is_valid('America/New_York'));
        $this->assertFalse(tsml_timezone_is_valid('Not/AZone'));
    }

    public function test_timezone_parse()
    {
        $this->assertSame('America/New_York', tsml_timezone_parse('America/New_York'));
        $this->assertSame('America/New_York', tsml_timezone_parse('america/new york'));
        $this->assertNull(tsml_timezone_parse('Nonexistent/Zone'));
    }

    public function test_date_localised_respects_site_timezone()
    {
        update_option('timezone_string', 'UTC');
        update_option('gmt_offset', 0);
        $this->assertSame('2024-01-01 00:00', tsml_date_localised('Y-m-d H:i', 1704067200));
    }
}
