<?php

/**
 *
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
 *
 * Render the timezone select menu
 * Used in admin_edit and settings
 *
 * @param $selected
 * @return string
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
            <optgroup label="<?php echo $continent ?>">
                <?php foreach ($cities as $timezone => $city) { ?>
                    <option value="<?php echo $timezone ?>" <?php selected($timezone, $selected) ?>>
                        <?php echo $city ?>
                    </option>
                <?php } ?>
            </optgroup>
        <?php } ?>
    </select>
    <?php
}
