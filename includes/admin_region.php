<?php
//customizing region administration

add_action('tsml_region_edit_form_fields', function ($term) {
?>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="delete_and_reassign"><?php _e('Delete and Reassign', '12-step-meeting-list'); ?></label>
        </th>
        <td>
            <?php wp_dropdown_categories([
                'taxonomy' => 'tsml_region',
                'hierarchical' => true,
                'orderby' => 'name',
                'exclude' => $term->term_id,
                'show_option_all' => '&nbsp;',
                'name' => 'delete_and_reassign',
                'id' => 'delete_and_reassign',
            ]);
            ?>
            <p class="description">
                <?php _e('Delete this region and reassign its locations to another region.', '12-step-meeting-list') ?>
            </p>
        </td>
    </tr>
<?php });

add_action(
    'edited_tsml_region',
    function ($region_id) {

        //set updated time for all meetings in region if a region is edited
        $meetings = tsml_get_meetings(['region' => $region_id]);
        foreach ($meetings as $meeting) {
            wp_update_post(['ID' => $meeting['id']]);
        }

        $delete_and_reassign = intval($_POST['delete_and_reassign']);

        //delete this region and reassign its locations to another region
        if (!empty($delete_and_reassign)) {
            $location_ids = get_posts([
                'post_type' => 'tsml_location',
                'numberposts' => -1,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => 'tsml_region',
                        'terms' => intval($region_id),
                    ],
                ],
            ]);

            //assign new region to each location
            foreach ($location_ids as $location_id) {
                wp_set_object_terms($location_id, $delete_and_reassign, 'tsml_region');
            }

            //delete term
            wp_delete_term($region_id, 'tsml_region');

            //redirect to regions list
            wp_safe_redirect(admin_url('edit-tags.php?taxonomy=tsml_region&post_type=tsml_location'));
            exit;
        }
    },
    10,
    2
);
