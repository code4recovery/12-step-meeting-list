<?php

/**
 * Explicit state/configuration store for TSML.
 *
 * Internal code should prefer tsml_state_get() and tsml_state_set() over reading
 * or writing loose $tsml_* globals. The globals are still exported as a legacy
 * compatibility API for themes, snippets, and integrations that already use
 * them.
 *
 * Legacy global synchronisation is intentionally request-local and access-based:
 * state writes export the new value to $GLOBALS immediately, while external
 * writes to registered legacy globals are imported only the next time that key
 * is read through TSML_State. This is not a PHP reference to every possible
 * local copy of a global value.
 */
class TSML_State
{
    const CATEGORY_CONFIGURATION = 'configuration';
    const CATEGORY_SETTING = 'wordpress_setting';
    const CATEGORY_RUNTIME = 'runtime_state';
    const CATEGORY_COMPAT = 'compatibility_api';

    private $values = [];
    private $categories = [];
    private $legacy_snapshots = [];
    private $legacy_importable = [];
    private $registered_keys = [];

    public function set($key, $value, $category = self::CATEGORY_RUNTIME, $sync_global = true)
    {
        $this->registered_keys[$key] = true;
        $this->values[$key] = $value;
        $this->categories[$key] = $category;

        if ($sync_global) {
            $this->export_global($key);
        }

        return $value;
    }

    public function set_many($values, $category = self::CATEGORY_RUNTIME, $sync_global = true)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $category, $sync_global);
        }
    }

    public function get_many($keys)
    {
        $values = [];

        foreach ($keys as $key => $default) {
            if (is_int($key)) {
                $key = $default;
                $default = null;
            }
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function get($key, $default = null)
    {
        $this->warn_unknown_key($key);
        $this->import_legacy_global($key);

        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function has($key)
    {
        $this->warn_unknown_key($key);
        $this->import_legacy_global($key);

        return array_key_exists($key, $this->values);
    }

    public function category($key)
    {
        return array_key_exists($key, $this->categories) ? $this->categories[$key] : null;
    }

    public function all()
    {
        foreach (array_keys($this->legacy_importable) as $key) {
            $this->import_legacy_global($key);
        }

        return $this->values;
    }

    public function register_legacy_globals($keys)
    {
        foreach ($keys as $key) {
            $this->registered_keys[$key] = true;
            $this->legacy_importable[$key] = true;
            $this->import_legacy_global($key, false);
        }
    }

    public function export_global($key)
    {
        if (!array_key_exists($key, $this->values)) {
            return;
        }

        $GLOBALS[$key] = $this->values[$key];
        $this->legacy_snapshots[$key] = $this->values[$key];
    }

    public function export_globals($keys = null)
    {
        $keys = $keys === null ? array_keys($this->values) : $keys;
        foreach ($keys as $key) {
            $this->export_global($key);
        }
    }

    private function import_legacy_global($key, $prefer_global = true)
    {
        if (empty($this->legacy_importable[$key]) || !array_key_exists($key, $GLOBALS)) {
            return;
        }

        if (!$prefer_global && array_key_exists($key, $this->values)) {
            return;
        }

        $global_value = $GLOBALS[$key];
        $has_snapshot = array_key_exists($key, $this->legacy_snapshots);
        $has_value = array_key_exists($key, $this->values);

        if (!$has_value || !$has_snapshot || $global_value !== $this->legacy_snapshots[$key]) {
            $this->values[$key] = $global_value;
            if (!array_key_exists($key, $this->categories)) {
                $this->categories[$key] = self::CATEGORY_COMPAT;
            }
        }

        $this->legacy_snapshots[$key] = $this->values[$key];
    }

    private function warn_unknown_key($key)
    {
        if (isset($this->registered_keys[$key]) || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        trigger_error('Unknown TSML state key: ' . $key, E_USER_NOTICE);
    }
}

function tsml_state()
{
    static $state = null;

    if ($state === null) {
        $state = new TSML_State();
    }

    return $state;
}

function tsml_state_get($key, $default = null)
{
    return tsml_state()->get($key, $default);
}

function tsml_state_get_many($keys)
{
    return tsml_state()->get_many($keys);
}

function tsml_state_set($key, $value, $category = TSML_State::CATEGORY_RUNTIME)
{
    return tsml_state()->set($key, $value, $category);
}

function tsml_state_set_many($values, $category = TSML_State::CATEGORY_RUNTIME)
{
    tsml_state()->set_many($values, $category);
}

function tsml_state_register_legacy_globals($keys)
{
    tsml_state()->register_legacy_globals($keys);
}

function tsml_state_export_globals($keys = null)
{
    tsml_state()->export_globals($keys);
}
