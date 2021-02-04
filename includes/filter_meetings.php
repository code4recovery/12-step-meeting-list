<?php

//this is a workaround because we can't use closures in order to support php < 5.3
class tsml_filter_meetings
{

    public $day;
    public $distance;
    public $distance_units;
    public $district_id;
    public $group_id;
    public $latitude;
    public $location_id;
    public $longitude;
    public $now;
    public $query;
    public $region_id;
    public $searchable_keys;
    public $time;
    public $type;

    //sanitize and save arguments (won't be passed to a database)
    public function __construct($arguments)
    {

        if (!empty($arguments['day']) || (isset($arguments['day']) && $arguments['day'] == 0)) {
            $this->day = is_array($arguments['day']) ? array_map('intval', $arguments['day']) : array(intval($arguments['day']));
        }

        if (!empty($arguments['district'])) {
            $this->district_id = is_array($arguments['district']) ? array_map('sanitize_title', $arguments['district']) : array(sanitize_title($arguments['district']));
            //we are recieving district slugs, need to convert to IDs (todo save this in the cache)
            $this->district_id = array_map(array($this, 'get_district_id'), $this->district_id);
            //district_id is now an array of arrays because districts can have children
            $return = array();
            foreach ($this->district_id as $district_id_array) {
                $return = array_merge($return, $district_id_array);
            }
            $this->district_id = $return;
        }

        if (!empty($arguments['group_id'])) {
            $this->group_id = is_array($arguments['group_id']) ? array_map('intval', $arguments['group_id']) : array(intval($arguments['group_id']));
        }

        if (!empty($arguments['latitude']) && !empty($arguments['longitude'])) {
            $this->latitude = floatval($arguments['latitude']);
            $this->longitude = floatval($arguments['longitude']);
            $this->distance_units = (!empty($arguments['longitude']) && $arguments['longitude'] == 'km') ? 'km' : 'mi';
            if (!empty($arguments['distance'])) {
                $this->distance = floatval($arguments['distance']);
            }
        }

        if (!empty($arguments['location_id'])) {
            $this->location_id = is_array($arguments['location_id']) ? array_map('intval', $arguments['location_id']) : array(intval($arguments['location_id']));
        }

        if (!empty($arguments['query'])) {
            $this->searchable_keys = array('name', 'notes', 'location', 'location_notes', 'formatted_address', 'group', 'group_notes');
            $this->query = array_map('sanitize_text_field', array_filter(array_unique(explode(' ', stripslashes($arguments['query'])))));
        }

        if (!empty($arguments['region'])) {
            $this->region_id = is_array($arguments['region']) ? array_map('sanitize_title', $arguments['region']) : array(sanitize_title($arguments['region']));
            //we are recieving region slugs, need to convert to IDs (todo save this in the cache)
            $this->region_id = array_map(array($this, 'get_region_id'), $this->region_id);
            //region_id is now an array of arrays because regions can have children
            $return = array();
            foreach ($this->region_id as $region_id_array) {
                $return = array_merge($return, $region_id_array);
            }
            $this->region_id = $return;
        }

        if (!empty($arguments['time'])) {
            $this->time = is_array($arguments['time']) ? array_map('sanitize_title', $arguments['time']) : array(sanitize_title($arguments['time']));
            if (in_array('upcoming', $this->time)) {
                if (!empty($arguments['offset'])) {
                    $timestamp = current_time('timestamp');
                    $secondsToAdd = $arguments['offset'] * 60;
                    $this->now =  date("H:i", $timestamp + $secondsToAdd);
                } else {
                   $this->now = current_time('H:i');
                }
            }
        }

        if (!empty($arguments['type'])) {
            $this->type = is_array($arguments['type']) ? array_map('trim', $arguments['type']) : explode(',',trim($arguments['type']));
        }

    }

    //run the filters
    public function apply($meetings)
    {

        //run filters
        if ($this->day) {
            $meetings = array_filter($meetings, array($this, 'filter_day'));
        }

        if ($this->district_id) {
            $meetings = array_filter($meetings, array($this, 'filter_district'));
        }

        if ($this->group_id) {
            $meetings = array_filter($meetings, array($this, 'filter_group'));
        }

        if ($this->location_id) {
            $meetings = array_filter($meetings, array($this, 'filter_location'));
        }

        if ($this->query) {
            $meetings = array_filter($meetings, array($this, 'filter_query'));
        }

        if ($this->region_id) {
            $meetings = array_filter($meetings, array($this, 'filter_region'));
        }

        if ($this->time) {
            $meetings = array_filter($meetings, array($this, 'filter_time'));
        }

        if ($this->type) {
            $meetings = array_filter($meetings, array($this, 'filter_type'));
        }

        //if lat and lon are set then compute distances
        if ($this->latitude && $this->longitude) {
            $meetings = array_map(array($this, 'calculate_distance'), $meetings);
            if ($this->distance) {
                $meetings = array_filter($meetings, array($this, 'filter_distance'));
            }

        }

        //return data
        return array_values($meetings);
    }

    //calculate distance to meeting
    public function calculate_distance($meeting)
    {
        if (!isset($meeting['latitude']) || !isset($meeting['longitude'])) {
            return $meeting;
        }

        $meeting['distance'] = rad2deg(acos(sin(deg2rad($this->latitude)) * sin(deg2rad($meeting['latitude'])) + cos(deg2rad($this->latitude)) * cos(deg2rad($meeting['latitude'])) * cos(deg2rad($this->longitude - $meeting['longitude'])))) * 69.09;
        if ($this->distance_units == 'km') {
            $meeting['distance'] *= 1.609344;
        }

        $meeting['distance'] = round($meeting['distance'], 1);
        return $meeting;
    }

    //callback function to pass to array_filter
    public function filter_day($meeting)
    {
        if (!isset($meeting['day'])) {
            return false;
        }

        return in_array($meeting['day'], $this->day);
    }

    //callback function to pass to array_filter
    public function filter_distance($meeting)
    {
        if (!isset($meeting['distance'])) {
            return false;
        }

        return $meeting['distance'] < $this->distance;
    }

    //callback function to pass to array_filter
    public function filter_district($meeting)
    {
        if (!isset($meeting['district_id'])) {
            return false;
        }

        return in_array($meeting['district_id'], $this->district_id);
    }

    //callback function to pass to array_filter
    public function filter_group($meeting)
    {
        if (!isset($meeting['group_id'])) {
            return false;
        }

        return in_array($meeting['group_id'], $this->group_id);
    }

    //callback function to pass to array_filter
    public function filter_location($meeting)
    {
        if (!isset($meeting['location_id'])) {
            return false;
        }

        return in_array($meeting['location_id'], $this->location_id);
    }

    //callback function to pass to array_filter
    public function filter_query($meeting)
    {
        foreach ($this->query as $word) {
            $word_matches = false;
            foreach ($this->searchable_keys as $key) {
                if (isset($meeting[$key]) && stripos($meeting[$key], $word) !== false) {
                    $word_matches = true;
                    break;
                }
            }
            if (!$word_matches) {
                return false;
            }

        }
        return true;
    }

    //callback function to pass to array_filter
    public function filter_region($meeting)
    {
        if (!isset($meeting['region_id'])) {
            return false;
        }

        return in_array($meeting['region_id'], $this->region_id);
    }

    //callback function to pass to array_filter
    public function filter_time($meeting)
    {
        if (!isset($meeting['time'])) {
            return false;
        }
        
        foreach ($this->time as $time) {
            if ($time == 'morning') {
                return (strcmp('04:00', $meeting['time']) <= 0 && strcmp('11:59', $meeting['time']) >= 0);
            } elseif ($time == 'midday') {
                return (strcmp('11:00', $meeting['time']) <= 0 && strcmp('16:59', $meeting['time']) >= 0);
            } elseif ($time == 'evening') {
                return (strcmp('16:00', $meeting['time']) <= 0 && strcmp('20:59', $meeting['time']) >= 0);
            } elseif ($time == 'night') {
                return (strcmp('20:00', $meeting['time']) <= 0 || strcmp('04:59', $meeting['time']) >= 0);
            } elseif ($time == 'upcoming') {
                return (strcmp($this->now, $meeting['time']) <= 0);
            }
        }
    }

    //callback function to pass to array_filter
    public function filter_type($meeting)
    {
        if (!isset($meeting['types'])) {
            return false;
        }
        return !count(array_diff($this->type, $meeting['types']));
    }

    //function to get district id from slug
    public function get_district_id($slug)
    {
        $term = get_term_by('slug', $slug, 'tsml_district');
        $children = get_term_children($term->term_id, 'tsml_district');
        return array_merge(array($term->term_id), $children);
    }

    //function to get region id from slug, as well as child region ids
    public function get_region_id($slug)
    {
        $term = get_term_by('slug', $slug, 'tsml_region');
        $children = get_term_children($term->term_id, 'tsml_region');
        return array_merge(array($term->term_id), $children);
    }

}
