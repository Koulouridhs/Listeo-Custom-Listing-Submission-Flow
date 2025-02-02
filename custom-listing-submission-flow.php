<?php
/**
 * Plugin Name: Custom Listing Submission Flow
 * Description: Rearranges the steps of the listing submission form to follow this flow: 1) Select Listing Type (with auto fetch), 2) Select Package, 3) Submit Details, 4) Preview, 5) Completion.
 * Version: 1.0
 * Author: George Koulouridhs
 * License: GPL2+
 */

/**
 * Rearranging the steps of listing submission.
 *
 * @param array $steps The original array of steps.
 * @return array The reordered array of steps.
 */
function cls_modify_submission_steps( $steps ) {
    // Creating a new step array in the desired order:
    // 1. 'type' (Listing Type)
    // 2. 'package' (or 'process-package', depending on what exists)
    // 3. 'submit' (Submit Details)
    // 4. 'preview' (Preview)
    // 5. 'done' (Completion)
    
    $new_steps = array();

    if ( isset( $steps['type'] ) ) {
        $new_steps['type'] = $steps['type'];
    }

    // If the step "package" or "process-package" exists, merge it as the "package" step.
    if ( isset( $steps['package'] ) ) {
        $new_steps['package'] = $steps['package'];
    } elseif ( isset( $steps['process-package'] ) ) {
        $new_steps['package'] = $steps['process-package'];
    }

    if ( isset( $steps['submit'] ) ) {
        $new_steps['submit'] = $steps['submit'];
    }
    if ( isset( $steps['preview'] ) ) {
        $new_steps['preview'] = $steps['preview'];
    }
    if ( isset( $steps['done'] ) ) {
        $new_steps['done'] = $steps['done'];
    }

    // If there are other steps not covered, add them to the end.
    foreach ( $steps as $key => $step ) {
        if ( ! isset( $new_steps[ $key ] ) ) {
            $new_steps[ $key ] = $step;
        }
    }

    // (Optional) Set new priorities for each step.
    if ( isset( $new_steps['type'] ) ) {
        $new_steps['type']['priority'] = 5;
    }
    if ( isset( $new_steps['package'] ) ) {
        $new_steps['package']['priority'] = 10;
    }
    if ( isset( $new_steps['submit'] ) ) {
        $new_steps['submit']['priority'] = 15;
    }
    if ( isset( $new_steps['preview'] ) ) {
        $new_steps['preview']['priority'] = 20;
    }
    if ( isset( $new_steps['done'] ) ) {
        $new_steps['done']['priority'] = 25;
    }

    return $new_steps;
}
add_filter( 'submit_listing_steps', 'cls_modify_submission_steps', 20 );

/**
 * Automated fetching (auto fetch) of Listing Types.
 *
 * @return array Array [slug => name] of listing types.
 */
function cls_auto_fetch_listing_types() {
    global $wpdb;
    
    $results = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_listing_type'" );
    
    $listing_types = array();
    if ( ! empty( $results ) ) {
        foreach ( $results as $value ) {
            $listing_types[$value] = ucfirst($value);
        }
    }
    
    if ( empty( $listing_types ) ) {
        $listing_types = array(
            'service'     => 'Service',
            'rental'      => 'Rental',
            'event'       => 'Event',
            'classifieds' => 'Classifieds'
        );
    }
    
    return $listing_types;
}



/**
 * Update the Listing Type selection field with dynamic values.
 *
 * @param array  $fields       Form fields array.
 * @param string $listing_type Current listing type (if exists).
 * @return array The modified fields.
 */
function cls_filter_listing_type_field( $fields, $listing_type ) {
    $auto_types = cls_auto_fetch_listing_types();

    // Look for the listing type selection field.
    // In this example, we assume there is a field with key "listing_type" in a group (e.g., 'basic_info').
    foreach ( $fields as $group_key => $group ) {
        if ( isset( $group['fields']['listing_type'] ) ) {
            $fields[ $group_key ]['fields']['listing_type']['options'] = $auto_types;
        }
    }
    return $fields;
}
add_filter( 'submit_listing_form_fields', 'cls_filter_listing_type_field', 10, 2 );

/**
 * Store the selected Listing Type in a cookie to be used later for filtering packages.
 */
function cls_store_listing_type() {
    if ( isset( $_POST['listing_type'] ) ) {
        setcookie( 'cls_listing_type', sanitize_text_field( $_POST['listing_type'] ), time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
    }
}
add_action( 'init', 'cls_store_listing_type' );

/**
 * Filter available packages based on the selected Listing Type.
 *
 * Here we assume that packages (WooCommerce products) store metadata
 * (e.g., 'allowed_listing_types') that indicate the listing types they are available for.
 *
 * @param array $packages The original array of packages.
 * @param int   $listing_id (optional) The ID of the listing.
 * @return array The filtered packages.
 */
function cls_filter_available_packages( $packages, $listing_id = 0 ) {
    if ( isset( $_COOKIE['cls_listing_type'] ) ) {
        $listing_type = sanitize_text_field( $_COOKIE['cls_listing_type'] );
        $filtered = array();

        foreach ( $packages as $package ) {
            // Assume each package has a meta field 'allowed_listing_types' stored as an array.
            $allowed_types = get_post_meta( $package->ID, 'allowed_listing_types', true );
            if ( is_array( $allowed_types ) && in_array( $listing_type, $allowed_types ) ) {
                $filtered[] = $package;
            }
        }
        return $filtered;
    }
    return $packages;
}
// Note: If the original plugin provides a filter for returning packages, use the appropriate one.
// add_filter( 'custom_filter_packages', 'cls_filter_available_packages', 10, 2 );

?>
