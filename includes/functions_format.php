<?php

/**
 * take a full address and return it formatted for the front-end
 * used on template pages
 * 
 * @param mixed $formatted_address
 * @param mixed $street_only
 * @return string
 */
function tsml_format_address($formatted_address, $street_only = false)
{
    $parts = explode(',', esc_attr($formatted_address));
    $parts = array_map('trim', $parts);
    if (in_array(end($parts), ['USA', 'US'])) {
        array_pop($parts);
        if (count($parts) > 1) {
            $state_zip = array_pop($parts);
            $parts[count($parts) - 1] .= ', ' . $state_zip;
        }
    }
    if ($street_only) {
        return array_shift($parts);
    }
    return implode('<br>', $parts);
}

/**
 * takes 0, 18:30 and returns Sunday, 6:30 pm (depending on your settings)
 * used on admin_edit.php, archive-meetings.php, single-meetings.php
 * 
 * @param mixed $day
 * @param mixed $time
 * @param mixed $separator
 * @param mixed $short
 * @return string
 */
function tsml_format_day_and_time($day, $time, $separator = ', ', $short = false)
{
    global $tsml_days;
    // translators: Appt is abbreviation for Appointment
    if (empty($tsml_days[$day]) || empty($time)) {
        return $short ? __('Appt', '12-step-meeting-list') : __('Appointment', '12-step-meeting-list');
    }
    return ($short ? substr($tsml_days[$day], 0, 3) : $tsml_days[$day]) . $separator . tsml_format_time($time);
}

/**
 * appends men or women (or custom flags) if type present
 * used on archive-meetings.php
 * 
 * @param mixed $name
 * @param mixed $types
 * @return mixed
 */
function tsml_format_name($name, $types = null)
{
    global $tsml_program, $tsml_programs;
    if (!is_array($types)) {
        $types = [];
    }
    if (empty($tsml_programs[$tsml_program]['flags']) || !is_array($tsml_programs[$tsml_program]['flags'])) {
        return $name;
    }
    $append = [];
    $meeting_is_online = in_array('ONL', $types);
    // Types assigned to the meeting passed to the function
    foreach ($types as $type) {
        // True if the type for the meeting exists in one of the predetermined flags
        $type_is_flagged = in_array($type, $tsml_programs[$tsml_program]['flags']);
        $type_not_tc_and_online = !($type === 'TC' && $meeting_is_online);

        if ($type_is_flagged && $type_not_tc_and_online) {
            $append[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }
    return count($append) ? $name . ' <small>' . implode(', ', $append) . '</small>' : $name;
}

/**
 * format notes with sanitized paragraphs and line breaks
 * 
 * @param mixed $notes
 * @return void
 */
function tsml_format_notes($notes)
{
    echo wpautop(nl2br(esc_html($notes)));
}

/**
 * get meeting types
 * used on archive-meetings.php
 * 
 * @param mixed $types
 * @return string
 */
function tsml_format_types($types = [])
{
    global $tsml_program, $tsml_programs;
    if (!is_array($types)) {
        $types = [];
    }
    $append = [];
    // Types assigned to the meeting passed to the function
    foreach ($types as $type) {
        // True if the type for the meeting exists in one of the predetermined flags
        $type_is_flagged = in_array($type, $tsml_programs[$tsml_program]['flags']);

        if ($type_is_flagged && $type != 'TC' && $type != 'ONL') {
            $append[] = $tsml_programs[$tsml_program]['types'][$type];
        }
    }

    return implode(', ', $append);
}

/**
 * takes 18:30 and returns 6:30 pm (depending on your settings)
 * used on tsml_get_meetings(), single-meetings.php, admin_lists.php
 * 
 * @param mixed $string
 * @return string
 */
function tsml_format_time($string)
{
    if (empty($string)) {
        return __('Appointment', '12-step-meeting-list');
    }
    if ($string == '12:00') {
        return __('Noon', '12-step-meeting-list');
    }
    if ($string == '23:59' || $string == '00:00') {
        return __('Midnight', '12-step-meeting-list');
    }
    $date = strtotime($string);
    return date(get_option('time_format'), $date);
}

/**
 * takes a time string, eg 6:30 pm, and returns 18:30
 * used on tsml_import(), tsml_time_duration()
 * 
 * @param mixed $string
 * @return string
 */
function tsml_format_time_reverse($string)
{
    $time_parts = date_parse($string);
    return sprintf('%02d', $time_parts['hour']) . ':' . sprintf('%02d', $time_parts['minute']);
}

/**
 * takes a website URL, eg https://www.groupname.org and returns the domain
 * used on single-meetings.php
 * 
 * @param mixed $url
 * @return mixed
 */
function tsml_format_domain($url)
{
    $parts = parse_url(strtolower($url));
    if (!$parts) {
        return $url;
    }
    if (substr($parts['host'], 0, 4) == 'www.') {
        return substr($parts['host'], 4);
    }
    return $parts['host'];
}
