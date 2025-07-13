<?php

/**
 * Check if the timezone is valid
 * Used in variables, save, and settings
 *
 * @param $timezone
 * @return bool
 */
function tsml_timezone_is_valid($timezone)
{
    return in_array($timezone, DateTimeZone::listIdentifiers());
}

/**
 * Render the timezone select menu
 * Used in admin_edit and settings
 *
 * @param $selected
 * @return void
 */
function tsml_timezone_select($selected = null)
{
    $continents = [];
    foreach (DateTimeZone::listIdentifiers() as $timezone) {
        $count_slashes = substr_count($timezone, '/');
        if ($count_slashes < 1) {
            continue;
        }
        list($continent, $city) = explode('/', $timezone, 2);
        if (!isset($continents[$continent])) {
            $continents[$continent] = [];
        }
        $continents[$continent][$timezone] = str_replace('_', ' ', str_replace('/', ' - ', $city));
    }
    $continents['UTC'] = ['UTC' => 'UTC'];
    ?>
    <select name="timezone" id="timezone">
        <option value="" <?php selected($timezone, null) ?>></option>
        <?php foreach ($continents as $continent => $cities) { ?>
            <optgroup label="<?php echo esc_attr($continent) ?>">
                <?php foreach ($cities as $timezone => $city) { ?>
                    <option value="<?php echo esc_attr($timezone) ?>" <?php selected($timezone, $selected) ?>>
                        <?php echo esc_html($city) ?>
                    </option>
                <?php } ?>
            </optgroup>
        <?php } ?>
    </select>
    <?php
}

/**
 * Given a string, attempt to return a supported DateTime timezone
 *
 * @param string $value
 * @return string|null Accepted DateTime timezone, or null if none matching
 */
function tsml_timezone_parse($value = null)
{
    global $tsml_timezone_aliases, $_tsml_timezone_matches;

    $_tsml_timezone_matches = (array) $_tsml_timezone_matches;
    if (isset($_tsml_timezone_matches[$value])) {
        return $_tsml_timezone_matches[$value];
    }

    $timezone = null;
    $all_timezones = DateTimeZone::listIdentifiers();
   
    // first look for exact match
    if (in_array($value, $all_timezones)) {
        $timezone = $value;
    }

    // next look for a single case-insensitive match
    if (!$timezone) {
        $value_match = strtolower(trim(strval($value)));
        $value_underscore = str_replace(' ', '_', $value_match);
        $matches = array_values(array_filter($all_timezones, function($tz) use ($value_underscore) {
            return false !== stripos($tz, $value_underscore);
        }));
        if (1 === count($matches)) {
            $timezone = $matches[0];
        }
    }

    // if we still don't have a match, check TZ aliases
    if (!$timezone) {
        foreach ($tsml_timezone_aliases as $tz_value => $tz_aliases) {
            $match = array_filter($tz_aliases, function($alias) use ($value_match) {
                return $value_match === strtolower($alias);
            });
            if (count($match)) {
                $timezone = $tz_value;
                break;
            }
        }
    }

    // cache result, less looping later
    $_tsml_timezone_matches[$value] = $timezone;
    return $timezone;
}