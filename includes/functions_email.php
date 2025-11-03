<?php

/**
 * Send off email for meeting change
 *
 * @param array   $email_to    Addresses to send to
 * @param WP_Post $meeting     Updated meeting
 * @param WP_Post $old_meeting Meeting before update
 * @return void
 */
function tsml_email_meeting_change($email_to, $meeting, $old_meeting)
{

    global $tsml_export_columns, $tsml_array_fields, $tsml_days;

    $user = wp_get_current_user();

    $update_type = 'update';
    if ('publish' === $meeting->post_status && 'publish' !== $old_meeting->post_status) {
        $update_type = 'publish';
    } elseif ('draft' === $meeting->post_status && 'draft' !== $old_meeting->post_status) {
        $update_type = 'draft';
    } elseif ('draft' === $meeting->post_status && 'draft' === $old_meeting->post_status) {
        // no email when staying in draft
        return;
    }

    $fields = array_keys($tsml_export_columns);
    $fields_changed = [];
    if ('publish' !== $update_type) {
        // for updates only share changed fields
        $fields_changed = tsml_compare_meetings($old_meeting, $meeting);
        if (null === $fields_changed) {
            return;
        }
        $fields = array_merge($fields, $fields_changed);
        $fields = array_unique($fields);
    }

    $message = ' <p>';
    $subject = '';
    if ('publish' === $update_type) {
        $subject = __('Meeting Published', '12-step-meeting-list');
        $message .= sprintf(
            // translators: 1: user display name, 2: meeting permalink, 3: site name
            __('This is to notify you that %1$s published a <a href="%2$s">meeting</a> on the %3$s site.', '12-step-meeting-list'),
            $user->display_name,
            get_permalink($meeting->ID),
            get_bloginfo('name')
        );
    } elseif ('draft' === $update_type) {
        $subject = __('Meeting moved to Draft', '12-step-meeting-list');
        $message .= sprintf(
            // translators: 1: user display name, 2: meeting permalink, 3: site name
            __('This is to notify you that %1$s saved a <a href="%2$s">meeting</a> as a draft on the %3$s site.', '12-step-meeting-list'),
            $user->display_name,
            get_permalink($meeting->ID),
            get_bloginfo('name')
        );
    } else {
        $subject = __('Meeting Updated', '12-step-meeting-list');
        $message .= sprintf(
            // translators: 1: user display name, 2: meeting permalink, 3: site name
            __('This is to notify you that %1$s updated a <a href="%2$s">meeting</a> on the %3$s site.', '12-step-meeting-list'),
            $user->display_name,
            get_permalink($meeting->ID),
            get_bloginfo('name')
        );
    }
    $subject .= ': ' . sanitize_text_field($meeting->name);
    $message .= '</p><table style="font:14px arial;width:100%;border-collapse:collapse;padding:0;">';

    foreach ($fields as $field) {
        $old = property_exists($old_meeting, $field) ? $old_meeting->$field : '';
        $new = property_exists($meeting, $field) ? $meeting->$field : '';

        if (in_array($field, $tsml_array_fields)) {
            if (is_array($new)) $new = implode(', ', $new);
            if (is_array($old)) $old = implode(', ', $old);
        } elseif ($field == 'day') {
            $old = in_array($old, ['0', '1', '2', '3', '4', '5', '6']) ? $tsml_days[$old] : __('Appointment', '12-step-meeting-list');
            $new = in_array($new, ['0', '1', '2', '3', '4', '5', '6']) ? $tsml_days[$new] : __('Appointment', '12-step-meeting-list');
        } elseif ($field == 'time' || $field == 'end_time') {
            $old = empty($old) ? '' : tsml_format_time($old);
            $new = empty($new) ? '' : tsml_format_time($new);
        }

        $field_name = !empty($tsml_export_columns[$field]) ? $tsml_export_columns[$field] : ucwords(str_replace('_', ' ', $field));

        if (in_array($field, $fields_changed)) {
            $message .= '<tr style="border:1px solid #999;background-color:#fff;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px">';
            if (!empty($old)) {
                $message .= '<strike style="color:#999">' . $old . '</strike> ';
            }
            $message .= $new . '</td></tr>';
        } elseif (!empty($new)) {
            $message .= '<tr style="border:1px solid #999;background-color:#eee;"><td style="width:150px;padding:5px">' . $field_name . '</td><td style="padding:5px">' . $new . '</td></tr>';
        }
    }
    $message .= '</table>';
    tsml_email($email_to, $subject, $message);
}