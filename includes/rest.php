<?php

// rest api functions

add_action('rest_api_init', function () {
    register_rest_route('tsml', '/meetings', [
        'methods' => 'GET',
        'callback' => 'tsml_rest_feed_endpoint',
        'args' => [
            'key' => [
                'required' => false,
                'type' => 'string',
            ],
        ],
    ]);
});

function tsml_rest_feed_endpoint($wp_rest_request)
{
    global $tsml_sharing, $tsml_sharing_keys;
    $key = $wp_rest_request->get_param('key');
    if ($tsml_sharing === 'open' || (is_string($key) && array_key_exists($key, (array) $tsml_sharing_keys))) {
        $meetings = tsml_get_meetings();
        return rest_ensure_response($meetings);
    }
    return new WP_Error('feed_restricted', __('This meeting list is restricted.', '12-step-meeting-list'), ['status' => 403]);
}
