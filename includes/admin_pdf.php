<?php

if (!function_exists('tsml_pdf_page')) {
    /**
     * Admin page for generating PDFs with region selection
     * Used by admin_menu.php
     */
    function tsml_pdf_page()
    {
        global $tsml_nonce, $tsml_sharing;

        // get all regions with hierarchy
        $regions = get_terms([
            'taxonomy' => 'tsml_region',
            'hide_empty' => false,
            'orderby' => 'name'
        ]);
        
        // Build hierarchical structure similar to wp_dropdown_categories
        $walker = new Walker_Category();
        $region_args = [
            'taxonomy' => 'tsml_region',
            'hide_empty' => false,
            'orderby' => 'name',
            'hierarchical' => true,
            'echo' => false
        ];
        
        // Get hierarchical list of all regions for display
        $all_regions = get_terms($region_args);

        // generate PDF link from selected regions
        $pdf_link = '';
        $selected_regions = [];

        ?>
        <div class="wrap tsml_admin_settings">
            <h1><?php esc_html_e('Generate PDF', '12-step-meeting-list') ?></h1>
            
            <noscript>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('JavaScript is required for automatic parent-child checkbox behavior. With JavaScript disabled, please manually select only the most specific regions you need.', '12-step-meeting-list') ?></p>
                </div>
            </noscript>

            <div style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Main content area (60%) -->
                <div style="flex: 0 0 60%;">
                    <div class="postbox">
                        <div class="inside">
                            <h2 style="margin-top: 0;"><?php esc_html_e('Select Regions', '12-step-meeting-list') ?></h2>
                            
                            <?php if (empty($regions)) { ?>
                                <p class="error">
                                    <?php esc_html_e('No regions found. Please create some regions first.', '12-step-meeting-list') ?>
                                </p>
                            <?php } else { ?>
                                <p>
                                    <?php esc_html_e('Select which regions to include in your PDF. Leave all unchecked to include all meetings.', '12-step-meeting-list') ?>
                                </p>
                                
                                <div style="margin: 15px 0;">
                                    <button type="button" id="tsml_select_all_granular" class="button" style="margin-right: 10px;">
                                        <?php esc_html_e('Select All', '12-step-meeting-list') ?>
                                    </button>
                                    <button type="button" id="tsml_deselect_all_granular" class="button">
                                        <?php esc_html_e('Deselect All', '12-step-meeting-list') ?>
                                    </button>
                                </div>

                                <div class="tsml_region_list_granular" style="max-height: 500px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fff; margin-bottom: 15px;">
                                    <?php 
                                    // Display hierarchical checkboxes
                                    if (!empty($all_regions)) {
                                        $hierarchy = _get_term_hierarchy('tsml_region');
                                        
                                        function tsml_display_region_tree_granular($regions, $hierarchy, $parent = 0, $level = 0, $selected = [], $parent_slug = '') {
                                            foreach ($regions as $region) {
                                                if ($region->parent != $parent) {
                                                    continue;
                                                }
                                                
                                                $style = $level > 0 ? 'padding-left: ' . ($level * 20) . 'px;' : '';
                                                ?>
                                                <label style="display: block; margin-bottom: 5px; <?php echo esc_attr($style) ?>">
                                                    <input type="checkbox" 
                                                        name="regions_granular[]"
                                                        value="<?php echo esc_attr($region->slug) ?>" 
                                                        data-region-slug="<?php echo esc_attr($region->slug) ?>"
                                                        data-parent-slug="<?php echo esc_attr($parent_slug) ?>"
                                                        class="tsml-region-checkbox-granular-mode"
                                                        <?php checked(in_array($region->slug, $selected)) ?>>
                                                    <?php echo esc_html($region->name) ?>
                                                </label>
                                                <?php
                                                
                                                // Display children if any exist
                                                if (isset($hierarchy[$region->term_id])) {
                                                    tsml_display_region_tree_granular($regions, $hierarchy, $region->term_id, $level + 1, $selected, $region->slug);
                                                }
                                            }
                                        }
                                        
                                        tsml_display_region_tree_granular($all_regions, $hierarchy, 0, 0, $selected_regions, '');
                                    }
                                    ?>
                                </div>

                                <form method="post" id="tsml_pdf_form_granular" target="_blank">
                                    <?php wp_nonce_field($tsml_nonce, 'tsml_nonce', false) ?>
                                    <p style="margin-bottom: 10px;">
                                        <button type="submit" name="tsml_generate_pdf_granular" class="button button-primary button-large">
                                            <?php esc_html_e('Generate PDF', '12-step-meeting-list') ?>
                                        </button>
                                        <label style="margin-left: 15px;">
                                            <input type="checkbox" id="tsml_debug_mode_granular" name="tsml_debug_mode_granular">
                                            <?php esc_html_e('Debug mode', '12-step-meeting-list') ?>
                                        </label>
                                    </p>
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar (40%) -->
                <div style="flex: 1;">
                    <div class="postbox">
                        <div class="inside">
                            <h3 style="margin-top: 0;"><?php esc_html_e('About PDF Generation', '12-step-meeting-list') ?></h3>
                            <p style="font-size: 13px;">
                                <?php esc_html_e('This tool generates a PDF of your meeting list filtered by region. The PDF is created by pdf.code4recovery.org using your public meeting data.', '12-step-meeting-list') ?>
                            </p>
                            <h4><?php esc_html_e('How it works:', '12-step-meeting-list') ?></h4>
                            <ul style="font-size: 13px; margin-left: 20px;">
                                <li><?php esc_html_e('Check parent regions to include all sub-regions', '12-step-meeting-list') ?></li>
                                <li><?php esc_html_e('Only the most specific selections are used', '12-step-meeting-list') ?></li>
                                <li><?php esc_html_e('Leave all unchecked for all meetings', '12-step-meeting-list') ?></li>
                                <li><?php esc_html_e('The PDF opens in a new tab', '12-step-meeting-list') ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        input[type="checkbox"]:indeterminate {
            opacity: 1;
            appearance: auto;
            -webkit-appearance: checkbox;
        }
        </style>
        
        <script>
        jQuery(function($) {
                            $('#tsml_select_all_granular').on('click', function() {
                                $('.tsml-region-checkbox-granular-mode').prop('checked', true).prop('indeterminate', false);
                            });
                            
                            $('#tsml_deselect_all_granular').on('click', function() {
                                $('.tsml-region-checkbox-granular-mode').prop('checked', false).prop('indeterminate', false);
                            });
                            
                            // When any checkbox changes in granular mode
                            $('.tsml-region-checkbox-granular-mode').on('change', function() {
                                var $checkbox = $(this);
                                var isChecked = $checkbox.prop('checked');
                                var regionSlug = $checkbox.data('region-slug');
                                var parentSlug = $checkbox.data('parent-slug');
                                
                                if (isChecked) {
                                    updateChildrenCheckboxesGranular(regionSlug, true);
                                } else {
                                    deselectAllParents(parentSlug);
                                    updateChildrenCheckboxesGranular(regionSlug, false);
                                }
                                
                                updateAllParentStates();
                            });
                            
                            function updateChildrenCheckboxesGranular(parentSlug, isChecked) {
                                $('.tsml-region-checkbox-granular-mode[data-parent-slug="' + parentSlug + '"]').each(function() {
                                    var $child = $(this);
                                    $child.prop('checked', isChecked);
                                    updateChildrenCheckboxesGranular($child.data('region-slug'), isChecked);
                                });
                            }
                            
                            function deselectAllParents(childSlug) {
                                if (!childSlug) return;
                                var $parentCheckbox = $('.tsml-region-checkbox-granular-mode[data-region-slug="' + childSlug + '"]');
                                if ($parentCheckbox.length) {
                                    $parentCheckbox.prop('checked', false);
                                    var grandparentSlug = $parentCheckbox.data('parent-slug');
                                    if (grandparentSlug) {
                                        deselectAllParents(grandparentSlug);
                                    }
                                }
                            }
                            
                            function updateAllParentStates() {
                                // Build a list of all parent regions with their depth level
                                var parentsByDepth = {};
                                var maxDepth = 0;
                                
                                $('.tsml-region-checkbox-granular-mode').each(function() {
                                    var parentSlug = $(this).data('parent-slug');
                                    if (!parentSlug) return;
                                    
                                    // Calculate depth by counting ancestors
                                    var depth = 0;
                                    var currentSlug = parentSlug;
                                    while (currentSlug) {
                                        depth++;
                                        var $current = $('.tsml-region-checkbox-granular-mode[data-region-slug="' + currentSlug + '"]');
                                        currentSlug = $current.data('parent-slug');
                                    }
                                    
                                    if (!parentsByDepth[depth]) {
                                        parentsByDepth[depth] = new Set();
                                    }
                                    parentsByDepth[depth].add(parentSlug);
                                    maxDepth = Math.max(maxDepth, depth);
                                });
                                
                                // Update parents from deepest to shallowest
                                for (var depth = maxDepth; depth >= 0; depth--) {
                                    if (parentsByDepth[depth]) {
                                        parentsByDepth[depth].forEach(function(parentSlug) {
                                            updateParentStateSingle(parentSlug);
                                        });
                                    }
                                }
                            }
                            
                            function updateParentStateSingle(parentSlug) {
                                var $parentCheckbox = $('.tsml-region-checkbox-granular-mode[data-region-slug="' + parentSlug + '"]');
                                if (!$parentCheckbox.length) return;
                                
                                var $children = $('.tsml-region-checkbox-granular-mode[data-parent-slug="' + parentSlug + '"]');
                                if ($children.length === 0) return;
                                
                                var checkedCount = $children.filter(':checked').length;
                                var indeterminateCount = 0;
                                
                                $children.each(function() {
                                    if ($(this).prop('indeterminate')) {
                                        indeterminateCount++;
                                    }
                                });
                                
                                var totalCount = $children.length;
                                
                                if (checkedCount === 0 && indeterminateCount === 0) {
                                    $parentCheckbox.prop('checked', false).prop('indeterminate', false);
                                } else if (checkedCount === totalCount && indeterminateCount === 0) {
                                    $parentCheckbox.prop('checked', true).prop('indeterminate', false);
                                } else {
                                    $parentCheckbox.prop('checked', false).prop('indeterminate', true);
                                }
                            }
                            
                            function getLeafRegions() {
                                var leafRegions = [];
                                var allChecked = $('.tsml-region-checkbox-granular-mode:checked');
                                
                                allChecked.each(function() {
                                    var regionSlug = $(this).val();
                                    var hasCheckedChildren = false;
                                    
                                    $('.tsml-region-checkbox-granular-mode:checked').each(function() {
                                        var potentialChild = $(this);
                                        var parentChain = potentialChild.data('parent-slug');
                                        
                                        var currentSlug = parentChain;
                                        while (currentSlug) {
                                            if (currentSlug === regionSlug) {
                                                hasCheckedChildren = true;
                                                return false;
                                            }
                                            var parentCheckbox = $('.tsml-region-checkbox-granular-mode[data-region-slug="' + currentSlug + '"]');
                                            currentSlug = parentCheckbox.data('parent-slug');
                                        }
                                    });
                                    
                                    if (!hasCheckedChildren) {
                                        leafRegions.push(regionSlug);
                                    }
                                });
                                
                                return leafRegions;
                            }
                            
                            // Build PDF URL for granular mode
                            $('#tsml_pdf_form_granular').on('submit', function(e) {
                                e.preventDefault();
                                
                                var selectedRegions = getLeafRegions();
                                var debugMode = $('#tsml_debug_mode_granular').is(':checked');
                                var jsonUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=meetings';
                                
                                <?php if ($tsml_sharing === 'restricted') { ?>
                                if (debugMode) {
                                    jsonUrl += '&nonce=<?php echo wp_create_nonce($tsml_nonce); ?>';
                                }
                                <?php } ?>
                                
                                if (selectedRegions.length > 0) {
                                    selectedRegions.forEach(function(region) {
                                        jsonUrl += '&region[]=' + encodeURIComponent(region);
                                    });
                                }
                                
                                if (debugMode) {
                                    window.open(jsonUrl, '_blank');
                                } else {
                                    var pdfUrl = 'https://pdf.code4recovery.org/?json=' + encodeURIComponent(jsonUrl);
                                    window.open(pdfUrl, '_blank');
                                }
                            });
                        });
                        </script>
        <?php
    }
}
