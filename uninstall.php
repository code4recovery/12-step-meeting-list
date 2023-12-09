<?php

//check
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

tsml_delete('everything');

//flush rewrite once more for good measure
flush_rewrite_rules();
