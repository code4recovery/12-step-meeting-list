<?php

//check
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

require_once trailingslashit(__DIR__) . '12-step-meeting-list.php';

tsml_delete('everything');

//flush rewrite once more for good measure
flush_rewrite_rules();
