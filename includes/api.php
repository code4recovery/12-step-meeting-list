<?php

add_action('rest_api_init', function () {
    register_rest_route('tsml/v1', '/meetings', [
        'methods' => 'GET',
        'callback' => 'tsml_rest_endpoint',
        'permission_callback' => 'tsml_rest_permission_check',
    ]);
});

function tsml_rest_endpoint()
{
    $meetings = tsml_get_meetings();
    return rest_ensure_response($meetings);
}

function tsml_rest_permission_check()
{
    global $tsml_sharing;
    return $tsml_sharing === 'open';
}
