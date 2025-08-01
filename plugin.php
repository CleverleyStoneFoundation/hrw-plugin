<?php

/**
 * Plugin Name: VibeMap Helper - Houston Restaurant Week
 * Plugin URI: https://vibemap.com
 * Description: Custom overrides for Houston Restaurant Week to integrate HRW restaurant data with VibeMap places
 * Version: 1.0.0
 * Author: VibeMap
 * Author URI: https://vibemap.com
 * License: Proprietary
 * Text Domain: vibemap-hrw-helper
 * 
 * This helper plugin requires the main VibeMap plugin to be active.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if VibeMap plugin is active
add_action('plugins_loaded', 'vibemap_hrw_check_dependencies', 20); // Use priority 20 to ensure main plugin loads first
function vibemap_hrw_check_dependencies()
{
    if (!class_exists('VibeMap_REST_API')) {
        add_action('admin_notices', 'vibemap_hrw_dependency_notice');
        return;
    }

    // Initialize our overrides
    vibemap_hrw_init();
}

function vibemap_hrw_dependency_notice()
{
?>
    <div class="notice notice-error">
        <p><?php _e('VibeMap Helper - Houston Restaurant Week requires the main VibeMap plugin to be active.', 'vibemap-hrw-helper'); ?></p>
    </div>
<?php
}

/**
 * Initialize HRW overrides
 */
function vibemap_hrw_init()
{
    // Load the optimized system
    $bootstrap_file = plugin_dir_path(__FILE__) . 'includes/bootstrap.php';
    if (file_exists($bootstrap_file)) {
        require_once $bootstrap_file;
        error_log('HRW: Loaded optimized bootstrap system');
    } else {
        error_log('HRW: WARNING - Bootstrap file not found, falling back to legacy system');
        // Fall back to the legacy system if bootstrap is missing
        add_filter('rest_request_after_callbacks', 'vibemap_hrw_modify_places_response', 20, 3);
    }

    // Enqueue our transform filters script
    add_action('wp_enqueue_scripts', 'vibemap_hrw_enqueue_transform_override', 999);
    add_action('admin_enqueue_scripts', 'vibemap_hrw_enqueue_transform_override', 999);

    // Add settings page - use priority 99 to ensure it runs after main VibeMap menu
    add_action('admin_menu', 'vibemap_hrw_add_settings_page', 99);
    add_action('admin_init', 'vibemap_hrw_register_settings');

    // Fix for incorrect menu URL
    add_filter('submenu_file', 'vibemap_hrw_fix_submenu_file');

    // Hook into VibeMap settings API response to add our settings
    add_filter('vibemap_settings_api_response', 'vibemap_hrw_add_settings_to_api');

    // Add custom CSS to pages with VibeMap blocks
    add_action('wp_head', 'vibemap_hrw_output_custom_css');
    add_action('admin_head', 'vibemap_hrw_output_custom_css');
}

/**
 * Enqueue HRW Frontend Optimizer and Custom CSS
 */
function vibemap_hrw_enqueue_transform_override()
{
    // Only enqueue on pages that should have VibeMap content
    if (!vibemap_hrw_should_load_css()) {
        return;
    }

    // Enqueue the frontend optimizer for performance improvements
    // DISABLED - Performance data shows API (7.5s) is bottleneck, not frontend (1.1s)
    // Frontend optimizer solves wrong problem and adds unnecessary overhead
    // wp_enqueue_script(
    //     'hrw-frontend-optimizer',
    //     plugin_dir_url(__FILE__) . 'assets/js/hrw-frontend-optimizer.js',
    //     [], // No dependencies - runs independently
    //     '2.0.0-' . time(), // Cache busting for testing
    //     false // Load in head for early initialization
    // );

    // Enqueue HRW Logo Fix (lightweight fallback logo detection)
    wp_enqueue_script(
        'hrw-logo-fix',
        plugin_dir_url(__FILE__) . 'assets/js/hrw-logo-fix.js',
        [], // No dependencies - pure JavaScript
        '1.0.0-' . time(), // Cache busting for testing
        true // Load in footer after DOM is ready
    );

    // Enqueue custom HRW CSS files (order matters for priority)
    // Load card enhancer first (lower priority)
    wp_enqueue_style(
        'hrw-card-enhancer-styles',
        plugin_dir_url(__FILE__) . 'assets/css/hrw-card-enhancer.css',
        [], // No dependencies
        '1.0.0-' . time(), // Cache busting and versioning
        'all' // Media type
    );

    // Load vibemap styles second with dependency (higher priority)
    wp_enqueue_style(
        'hrw-vibemap-styles',
        plugin_dir_url(__FILE__) . 'assets/css/vibemap.css',
        ['hrw-card-enhancer-styles'], // Depends on card enhancer, loads after it
        '1.0.0-' . time(), // Cache busting and versioning
        'all' // Media type
    );

    // Enqueue Gutenberg/React timing for performance debugging
    wp_enqueue_script(
        'hrw-gutenberg-timing',
        plugin_dir_url(__FILE__) . 'assets/js/hrw-gutenberg-timing.js',
        array(),
        '1.2.0-' . time(), // Cache busting for testing
        false // Load in head for early initialization
    );

    // Add performance monitoring flag for debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_add_inline_script(
            'hrw-gutenberg-timing',
            'console.log("🎯 HRW Gutenberg Timing loaded for frontend performance debugging");',
            'after'
        );
    }
}

/**
 * Get taxonomy configuration (icon only)
 */
function vibemap_hrw_get_taxonomy_config($taxonomy_slug)
{
    $config_json = get_option('vibemap_hrw_taxonomy_config', '{}');
    $config = json_decode($config_json, true) ?: [];

    if (isset($config[$taxonomy_slug])) {
        return $config[$taxonomy_slug];
    } else {
        return ['icon' => 'tag'];  // Generic default icon
    }
}

/**
 * Get available VibeMap icons for taxonomy configuration
 */
function vibemap_hrw_get_available_icons()
{
    return [
        'all' => 'All/Default',
        'arts' => 'Arts',
        'art' => 'Art',
        'bar' => 'Bar',
        'bolt' => 'Bolt/Energy',
        'cafe' => 'Cafe',
        'comedy' => 'Comedy',
        'community' => 'Community',
        'concert' => 'Concert',
        'dance' => 'Dance',
        'discover' => 'Discover',
        'drink' => 'Drink',
        'entertainment' => 'Entertainment',
        'events' => 'Events',
        'favorite' => 'Favorite',
        'features' => 'Features',
        'film' => 'Film',
        'fitness' => 'Fitness',
        'food' => 'Food',
        'games' => 'Games',
        'gem' => 'Gem',
        'gym' => 'Gym',
        'health' => 'Health',
        'heart' => 'Heart',
        'hotel' => 'Hotel',
        'learning' => 'Learning',
        'location' => 'Location',
        'music' => 'Music',
        'nightlife' => 'Nightlife',
        'outdoors' => 'Outdoors',
        'residential' => 'Residential',
        'shopping' => 'Shopping',
        'shop' => 'Shop',
        'smoking' => 'Smoking',
        'spiritual' => 'Spiritual',
        'sports' => 'Sports',
        'stay' => 'Stay',
        'style' => 'Style',
        'tag' => 'Tag',
        'user' => 'User',
        'visit' => 'Visit',
        'walk' => 'Walk'
    ];
}


/**
 * Modify the places-data endpoint response after it's been processed
 */
function vibemap_hrw_modify_places_response($response, $handler, $request)
{
    error_log('HRW: Starting to modify places-data response');
    // Only modify our specific endpoint
    if ($request->get_route() !== '/vibemap/v1/places-data') {
        return $response;
    }

    // Enable logging
    error_log('HRW: Starting to modify places-data response');

    // Check if we got a valid response
    if (is_wp_error($response)) {
        error_log('HRW: Response is WP_Error: ' . $response->get_error_message());
        return $response;
    }

    // Get the original data
    $original_data = $response->get_data();

    // Log original data structure
    error_log('HRW: Original data from VibeMap API:');
    error_log('HRW: - Places count: ' . (isset($original_data['places']) ? count($original_data['places']) : 'N/A'));
    error_log('HRW: - Categories count: ' . (isset($original_data['categories']) ? count($original_data['categories']) : 'N/A'));
    error_log('HRW: - Vibes count: ' . (isset($original_data['vibes']) ? count($original_data['vibes']) : 'N/A'));
    error_log('HRW: - Tags count: ' . (isset($original_data['tags']) ? count($original_data['tags']) : 'N/A'));
    error_log('HRW: - Total count: ' . (isset($original_data['total_count']) ? $original_data['total_count'] : 'N/A'));

    // Log a sample place if available
    if (!empty($original_data['places'])) {
        $sample_place = $original_data['places'][0];
        error_log('HRW: Sample VibeMap place: ' . json_encode([
            'id' => $sample_place['id'] ?? 'N/A',
            'title' => $sample_place['title'] ?? 'N/A',
            'vibemap_place_id' => $sample_place['meta']['vibemap_place_id'] ?? 'N/A'
        ]));
    }

    // Merge HRW data with VibeMap data
    $merged_data = vibemap_hrw_merge_restaurant_data($original_data, $request);

    // Log merged data structure
    error_log('HRW: Merged data after processing:');
    error_log('HRW: - Places count: ' . (isset($merged_data['places']) ? count($merged_data['places']) : 'N/A'));
    error_log('HRW: - Categories count: ' . (isset($merged_data['categories']) ? count($merged_data['categories']) : 'N/A'));
    error_log('HRW: - Vibes count: ' . (isset($merged_data['vibes']) ? count($merged_data['vibes']) : 'N/A'));
    error_log('HRW: - Tags count: ' . (isset($merged_data['tags']) ? count($merged_data['tags']) : 'N/A'));
    error_log('HRW: - Taxonomies count: ' . (isset($merged_data['taxonomies']) ? count($merged_data['taxonomies']) : 'N/A'));

    // Add debug flag to help track transformation on frontend
    $merged_data['debug_info'] = [
        'hrw_modified' => true,
        'timestamp' => current_time('mysql'),
        'places_processed' => count($merged_data['places']),
        'coordinates_sample' => !empty($merged_data['places']) ? [
            'lat' => $merged_data['places'][0]['meta']['vibemap_place_latitude'] ?? 'N/A',
            'lng' => $merged_data['places'][0]['meta']['vibemap_place_longitude'] ?? 'N/A',
            'lat_type' => gettype($merged_data['places'][0]['meta']['vibemap_place_latitude'] ?? null),
            'lng_type' => gettype($merged_data['places'][0]['meta']['vibemap_place_longitude'] ?? null)
        ] : 'No places'
    ];

    // Add transform check data to help debug
    if (!empty($merged_data['places'])) {
        $first_place = $merged_data['places'][0];
        $merged_data['_transform_check'] = [
            'message' => 'If transform works, this place should have geometry.coordinates',
            'place_id' => $first_place['meta']['vibemap_place_id'] ?? 'N/A',
            'raw_lat' => $first_place['meta']['vibemap_place_latitude'] ?? 'N/A',
            'raw_lng' => $first_place['meta']['vibemap_place_longitude'] ?? 'N/A',
            'expected_geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    floatval($first_place['meta']['vibemap_place_longitude'] ?? 0),
                    floatval($first_place['meta']['vibemap_place_latitude'] ?? 0)
                ]
            ]
        ];
    }

    // Return modified response
    $response->set_data($merged_data);
    return $response;
}

/**
 * Merge HRW restaurant data with VibeMap places
 * 
 * LEGACY: This function is only used as a fallback when bootstrap.php is missing.
 * The optimized version in class-hrw-data-merger.php should be used instead.
 */
function vibemap_hrw_merge_restaurant_data($original_data, $request)
{
    error_log('HRW: Starting merge process - ONLY showing HRW restaurants');

    // Check if hrw_restaurants post type exists
    if (!post_type_exists('hrw_restaurants')) {
        error_log('HRW: ERROR - hrw_restaurants post type does not exist!');
        error_log('HRW: Available post types: ' . implode(', ', get_post_types()));
        // Return empty results since we only want HRW restaurants
        return [
            'places'     => [],
            'categories' => [],
            'vibes'      => [],
            'tags'       => [],
            'taxonomies' => []
        ];
    }

    // Get HRW restaurants with memory-efficient approach
    $hrw_args = [
        'post_type'      => 'hrw_restaurants',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids', // Only get IDs first to save memory
        'no_found_rows'  => true,  // Skip pagination count
        'cache_results'  => false,  // Don't cache
    ];

    // Apply any filtering from the original request
    $allParams = $request->get_query_params();
    error_log('HRW: Request params: ' . json_encode($allParams));

    // Get HRW restaurant IDs only
    $hrw_ids = get_posts($hrw_args);
    error_log('HRW: Found ' . count($hrw_ids) . ' total HRW restaurant IDs');

    // Filter IDs by meta values
    $filtered_ids = [];
    foreach ($hrw_ids as $id) {
        $menu_year = get_post_meta($id, '_menu_year', true);
        $menu_status = get_post_meta($id, '_menu_status', true);

        // Check if this restaurant matches our criteria
        if ($menu_year === '2025' && $menu_status === '4') {
            $filtered_ids[] = $id;
        }
    }

    error_log('HRW: Found ' . count($filtered_ids) . ' HRW restaurants matching year 2025 and status 4');

    // Now get the full post objects for only the filtered restaurants
    $hrw_restaurants = [];
    if (!empty($filtered_ids)) {
        $hrw_restaurants = get_posts([
            'post_type' => 'hrw_restaurants',
            'post__in' => $filtered_ids,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    // Get all HRW custom taxonomies
    $hrw_taxonomies = vibemap_hrw_get_custom_taxonomies();
    error_log('HRW: Found ' . count($hrw_taxonomies) . ' HRW custom taxonomies');

    if (empty($hrw_restaurants)) {
        // If no HRW restaurants, return empty results
        error_log('HRW: No HRW restaurants found, returning empty results');
        return [
            'places'     => [],
            'categories' => [],
            'vibes'      => [],
            'tags'       => [],
            'taxonomies' => $hrw_taxonomies
        ];
    }

    // Create a map of vibemap_place_id to vibemap place data
    $vibemap_places_map = [];
    if (!empty($original_data['places'])) {
        foreach ($original_data['places'] as $place) {
            if (isset($place['meta']['vibemap_place_id'])) {
                $vibemap_places_map[$place['meta']['vibemap_place_id']] = $place;
            }
        }
        error_log('HRW: Created map of ' . count($vibemap_places_map) . ' VibeMap places by vibemap_place_id');
    } else {
        error_log('HRW: No original places to map');
    }

    // Build merged data
    $merged_places = [];
    $used_categories = [];
    $used_vibes = [];
    $used_tags = [];
    $used_custom_taxonomies = []; // Track used custom taxonomy terms

    foreach ($hrw_restaurants as $index => $hrw_restaurant) {
        // Log first restaurant's meta to debug
        if ($index === 0) {
            $all_meta = get_post_meta($hrw_restaurant->ID);
            error_log('HRW: Sample restaurant meta fields for "' . $hrw_restaurant->post_title . '":');
            foreach ($all_meta as $key => $value) {
                error_log('HRW:   - ' . $key . ': ' . (is_array($value) ? json_encode($value) : $value));
            }

            // Debug ACF field storage
            error_log('HRW: Checking vibemap_id storage:');
            error_log('HRW:   - get_post_meta(vibemap_id): ' . var_export(get_post_meta($hrw_restaurant->ID, 'vibemap_id', true), true));
            error_log('HRW:   - get_post_meta(_vibemap_id): ' . var_export(get_post_meta($hrw_restaurant->ID, '_vibemap_id', true), true));

            // Also check if ACF is available
            if (function_exists('get_field')) {
                error_log('HRW: ACF is available');
                $acf_vibemap_id = get_field('vibemap_id', $hrw_restaurant->ID);
                error_log('HRW: ACF get_field(vibemap_id): ' . var_export($acf_vibemap_id, true));
            } else {
                error_log('HRW: WARNING - ACF get_field function not available!');
            }
        }

        // Get the vibemap_id from ACF field or post meta (optional now)
        $vibemap_id = null;
        if (function_exists('get_field')) {
            $vibemap_id = get_field('vibemap_id', $hrw_restaurant->ID);
        }

        // Fallback to direct post meta if ACF isn't working
        if (empty($vibemap_id)) {
            // Try without underscore first (this is where ACF stores the actual value)
            $vibemap_id = get_post_meta($hrw_restaurant->ID, 'vibemap_id', true);
        }

        // Check various possible field names (but skip underscore versions for ACF fields)
        if (empty($vibemap_id)) {
            $possible_fields = ['vibemap_place_id'];
            foreach ($possible_fields as $field) {
                $vibemap_id = get_post_meta($hrw_restaurant->ID, $field, true);
                if (!empty($vibemap_id)) {
                    error_log('HRW: Found vibemap_id in field: ' . $field);
                    break;
                }
            }
        }

        // If no vibemap_id, use the WordPress post ID as the identifier
        if (empty($vibemap_id)) {
            $vibemap_id = 'hrw_' . $hrw_restaurant->ID;
            error_log('HRW: No vibemap_id for "' . $hrw_restaurant->post_title . '", using ' . $vibemap_id);
        } else {
            error_log('HRW: Processing HRW restaurant "' . $hrw_restaurant->post_title . '" with vibemap_id: ' . $vibemap_id);
        }

        // Check if this restaurant matches an existing VibeMap place
        if (!empty($vibemap_id) && isset($vibemap_places_map[$vibemap_id]) && strpos($vibemap_id, 'hrw_') !== 0) {
            // This restaurant has a matching VibeMap place - merge them
            $vibemap_place = $vibemap_places_map[$vibemap_id];

            // Start with the COMPLETE VibeMap place structure - keep everything
            $merged_place = $vibemap_place;

            // Only override specific fields from HRW (minimal overrides)
            $merged_place['title'] = $hrw_restaurant->post_title;
            $merged_place['slug'] = $hrw_restaurant->post_name;
            $merged_place['permalink'] = get_permalink($hrw_restaurant->ID);

            // Use HRW content if not empty
            if (!empty($hrw_restaurant->post_content)) {
                $merged_place['content'] = $hrw_restaurant->post_content;
            }

            // Use HRW featured image if available
            $hrw_featured_image = get_the_post_thumbnail_url($hrw_restaurant->ID, 'full');
            if ($hrw_featured_image) {
                $merged_place['featured_image'] = $hrw_featured_image;
            }

            // Keep the original VibeMap ID - this is critical for the map to work
            // The HRW post ID goes in meta for reference
            $merged_place['meta']['hrw_post_id'] = $hrw_restaurant->ID;
            $merged_place['meta']['is_hrw_restaurant'] = true;

            // Get HRW address if available and override the VibeMap address fields
            if (function_exists('get_field')) {
                $hrw_address = get_field('full_address', $hrw_restaurant->ID);
                if (!empty($hrw_address) && is_array($hrw_address)) {
                    // Override VibeMap address fields with HRW data
                    if (isset($hrw_address['address'])) {
                        $merged_place['meta']['vibemap_place_address'] = $hrw_address['address'];
                        $merged_place['meta']['vibemap_place_full_address'] = $hrw_address['address'];
                    }
                    if (isset($hrw_address['lat']) && isset($hrw_address['lng'])) {
                        $merged_place['meta']['vibemap_place_latitude'] = (string)$hrw_address['lat'];
                        $merged_place['meta']['vibemap_place_longitude'] = (string)$hrw_address['lng'];
                        error_log('HRW: Updated coordinates from HRW address: lat=' . $hrw_address['lat'] . ', lng=' . $hrw_address['lng']);
                    }
                }
            }

            // Also check for direct latitude/longitude fields from ACF and ensure they're strings, not arrays
            $hrw_lat = get_post_meta($hrw_restaurant->ID, 'latitude', true);
            $hrw_lng = get_post_meta($hrw_restaurant->ID, 'longitude', true);

            if (!empty($hrw_lat) && !empty($hrw_lng)) {
                // Extract from array if needed
                if (is_array($hrw_lat)) {
                    $hrw_lat = reset($hrw_lat);
                }
                if (is_array($hrw_lng)) {
                    $hrw_lng = reset($hrw_lng);
                }

                $merged_place['meta']['vibemap_place_latitude'] = (string)$hrw_lat;
                $merged_place['meta']['vibemap_place_longitude'] = (string)$hrw_lng;
                error_log('HRW: Set coordinates from direct lat/lng fields: lat=' . $hrw_lat . ', lng=' . $hrw_lng);
            }

            // Get HRW custom taxonomies and add them to the place
            $hrw_custom_taxonomies = vibemap_hrw_get_place_custom_taxonomies($hrw_restaurant->ID);

            error_log('HRW: Custom taxonomies for "' . $hrw_restaurant->post_title . '": ' . json_encode($hrw_custom_taxonomies));

            // Add HRW taxonomies to the merged place
            foreach ($hrw_custom_taxonomies as $tax_slug => $terms) {
                // Store in meta with proper prefix for the map to access
                $merged_place['meta']['vibemap_place_' . $tax_slug] = $terms;

                // Also add as top-level properties for filtering
                $merged_place[$tax_slug] = $terms;

                // Track used terms
                foreach ($terms as $term) {
                    if (!isset($used_custom_taxonomies[$tax_slug])) {
                        $used_custom_taxonomies[$tax_slug] = [];
                    }
                    $used_custom_taxonomies[$tax_slug][$term['id']] = $term;
                }
            }

            // Track used taxonomies for filtering (keep original VibeMap taxonomies)
            if (isset($merged_place['categories'])) {
                foreach ($merged_place['categories'] as $cat) {
                    $used_categories[$cat['id']] = true;
                }
            }
            if (isset($merged_place['vibes'])) {
                foreach ($merged_place['vibes'] as $vibe) {
                    $used_vibes[$vibe['id']] = true;
                }
            }
            if (isset($merged_place['tags'])) {
                foreach ($merged_place['tags'] as $tag) {
                    $used_tags[$tag['id']] = true;
                }
            }

            // Log the final merged place for debugging
            error_log('HRW: Final merged place data for "' . $merged_place['title'] . '":');
            error_log('HRW: - ID: ' . ($merged_place['id'] ?? 'N/A'));
            error_log('HRW: - vibemap_place_id: ' . ($merged_place['meta']['vibemap_place_id'] ?? 'N/A'));
            error_log('HRW: - latitude: ' . ($merged_place['meta']['vibemap_place_latitude'] ?? 'N/A'));
            error_log('HRW: - longitude: ' . ($merged_place['meta']['vibemap_place_longitude'] ?? 'N/A'));
            error_log('HRW: - latitude type: ' . gettype($merged_place['meta']['vibemap_place_latitude'] ?? null));
            error_log('HRW: - longitude type: ' . gettype($merged_place['meta']['vibemap_place_longitude'] ?? null));
            error_log('HRW: - address: ' . ($merged_place['meta']['vibemap_place_address'] ?? 'N/A'));
            error_log('HRW: - is_hrw_restaurant: ' . ($merged_place['meta']['is_hrw_restaurant'] ? 'true' : 'false'));
            error_log('HRW: - Full merged place structure: ' . json_encode($merged_place, JSON_PRETTY_PRINT));

            // Add to results
            $merged_places[] = $merged_place;
        } else {
            // No matching VibeMap place - create a standalone HRW restaurant entry
            error_log('HRW: Creating standalone entry for "' . $hrw_restaurant->post_title . '" with ID: ' . $vibemap_id);

            // Create a place structure from scratch for this HRW restaurant
            $merged_place = [
                'id' => $hrw_restaurant->ID,
                'title' => $hrw_restaurant->post_title,
                'slug' => $hrw_restaurant->post_name,
                'permalink' => get_permalink($hrw_restaurant->ID),
                'content' => $hrw_restaurant->post_content,
                'featured_image' => '',
                'categories' => [],
                'vibes' => [],
                'tags' => [],
                'meta' => [
                    'vibemap_place_id' => $vibemap_id,
                    'vibemap_place_latitude' => '',
                    'vibemap_place_longitude' => '',
                    'vibemap_place_address' => '',
                    'vibemap_place_full_address' => '',
                    'vibemap_place_city' => 'Houston',
                    'vibemap_place_state' => 'TX',
                    'vibemap_place_neighborhood' => '',
                    'vibemap_place_images' => '[]',
                    'hrw_post_id' => $hrw_restaurant->ID,
                    'is_hrw_restaurant' => true
                ]
            ];

            // Get HRW address and coordinates if available
            if (function_exists('get_field')) {
                $hrw_address = get_field('full_address', $hrw_restaurant->ID);
                if (!empty($hrw_address) && is_array($hrw_address)) {
                    if (isset($hrw_address['address'])) {
                        $merged_place['meta']['vibemap_place_address'] = $hrw_address['address'];
                        $merged_place['meta']['vibemap_place_full_address'] = $hrw_address['address'];
                    }
                    if (isset($hrw_address['lat']) && isset($hrw_address['lng'])) {
                        $merged_place['meta']['vibemap_place_latitude'] = (string)$hrw_address['lat'];
                        $merged_place['meta']['vibemap_place_longitude'] = (string)$hrw_address['lng'];
                    }
                }
            }

            // Check for direct latitude/longitude fields
            $hrw_lat = get_post_meta($hrw_restaurant->ID, 'latitude', true);
            $hrw_lng = get_post_meta($hrw_restaurant->ID, 'longitude', true);

            if (!empty($hrw_lat) && !empty($hrw_lng)) {
                if (is_array($hrw_lat)) {
                    $hrw_lat = reset($hrw_lat);
                }
                if (is_array($hrw_lng)) {
                    $hrw_lng = reset($hrw_lng);
                }

                $merged_place['meta']['vibemap_place_latitude'] = (string)$hrw_lat;
                $merged_place['meta']['vibemap_place_longitude'] = (string)$hrw_lng;
            }

            // Get featured image using the new logic
            $featured_image_url = '';

            // First try ACF gallery field
            if (function_exists('get_field')) {
                $gallery_images = get_field('photos_of_hrw_menu_items', $hrw_restaurant->ID);
                error_log('HRW: Gallery images: ' . json_encode($gallery_images));
                if (!empty($gallery_images) && is_array($gallery_images) && count($gallery_images) > 0) {
                    $first_image = $gallery_images[0];

                    if (is_array($first_image) && isset($first_image['url'])) {
                        $featured_image_url = $first_image['url'];
                    } elseif (is_numeric($first_image)) {
                        $featured_image_url = wp_get_attachment_url($first_image);
                    }
                }
            }

            // Try WordPress featured image
            if (empty($featured_image_url)) {
                error_log('HRW: No featured image found, trying WordPress featured image');
                $featured_image_url = get_the_post_thumbnail_url($hrw_restaurant->ID, 'full') ?:
                    get_the_post_thumbnail_url($hrw_restaurant->ID, 'large') ?:
                    get_the_post_thumbnail_url($hrw_restaurant->ID, 'medium') ?: '';
            }

            // Use fallback image from settings
            if (empty($featured_image_url)) {
                error_log('HRW: No featured image found, trying fallback image');
                $featured_image_url = get_option('vibemap_hrw_fallback_image', '');
            }

            $merged_place['featured_image'] = $featured_image_url;

            // Get HRW custom taxonomies
            $hrw_custom_taxonomies = vibemap_hrw_get_place_custom_taxonomies($hrw_restaurant->ID);

            // Add HRW taxonomies to the merged place
            foreach ($hrw_custom_taxonomies as $tax_slug => $terms) {
                $merged_place['meta']['vibemap_place_' . $tax_slug] = $terms;
                $merged_place[$tax_slug] = $terms;

                foreach ($terms as $term) {
                    if (!isset($used_custom_taxonomies[$tax_slug])) {
                        $used_custom_taxonomies[$tax_slug] = [];
                    }
                    $used_custom_taxonomies[$tax_slug][$term['id']] = $term;
                }
            }

            // Only add if we have valid coordinates
            if (
                !empty($merged_place['meta']['vibemap_place_latitude']) &&
                !empty($merged_place['meta']['vibemap_place_longitude']) &&
                $merged_place['meta']['vibemap_place_latitude'] !== '0' &&
                $merged_place['meta']['vibemap_place_longitude'] !== '0'
            ) {
                $merged_places[] = $merged_place;
                error_log('HRW: Added standalone HRW restaurant "' . $merged_place['title'] . '" to results');
            } else {
                error_log('HRW: Skipping "' . $hrw_restaurant->post_title . '" - no valid coordinates');
            }
        }
    }

    // Filter original taxonomies to only include those used by merged places
    $filtered_categories = [];
    $filtered_vibes = [];
    $filtered_tags = [];

    if (!empty($original_data['categories'])) {
        foreach ($original_data['categories'] as $category) {
            if (isset($used_categories[$category['id']])) {
                $filtered_categories[] = $category;
            }
        }
    }

    if (!empty($original_data['vibes'])) {
        foreach ($original_data['vibes'] as $vibe) {
            if (isset($used_vibes[$vibe['id']])) {
                $filtered_vibes[] = $vibe;
            }
        }
    }

    if (!empty($original_data['tags'])) {
        foreach ($original_data['tags'] as $tag) {
            if (isset($used_tags[$tag['id']])) {
                $filtered_tags[] = $tag;
            }
        }
    }

    // Apply any preview limit from the original request
    $preview_limit = $request->get_param('preview_limit');
    if ($preview_limit && $preview_limit > 0 && count($merged_places) > $preview_limit) {
        error_log('HRW: Applying preview limit of ' . $preview_limit);
        $merged_places = array_slice($merged_places, 0, $preview_limit);
    }

    // Build HRW custom taxonomies for the response
    $used_hrw_taxonomies = [];
    foreach ($used_custom_taxonomies as $tax_slug => $terms) {
        $all_terms = array_values($terms);
        if (!empty($all_terms)) {
            // Get the taxonomy info from the full list
            if (isset($hrw_taxonomies[$tax_slug])) {
                $used_hrw_taxonomies[$tax_slug] = $hrw_taxonomies[$tax_slug];
                $used_hrw_taxonomies[$tax_slug]['terms'] = $all_terms;
            }
        }
    }

    error_log('HRW: Final results:');
    error_log('HRW: - Total HRW restaurants checked: ' . count($hrw_restaurants));
    error_log('HRW: - HRW restaurants with VibeMap matches returned: ' . count($merged_places));
    error_log('HRW: - Categories from matched places: ' . count($filtered_categories));
    error_log('HRW: - Vibes from matched places: ' . count($filtered_vibes));
    error_log('HRW: - Tags from matched places: ' . count($filtered_tags));
    error_log('HRW: - HRW custom taxonomies in use: ' . count($used_hrw_taxonomies));

    // Debug original taxonomies
    error_log('HRW: Original taxonomies from API: ' . json_encode($original_data['taxonomies'] ?? []));
    error_log('HRW: Used HRW taxonomies: ' . json_encode(array_keys($used_hrw_taxonomies)));

    // Filter out empty taxonomies from original data
    $filtered_original_taxonomies = [];
    if (!empty($original_data['taxonomies'])) {
        foreach ($original_data['taxonomies'] as $tax_key => $tax_data) {
            // Only include taxonomies that have terms
            if (!empty($tax_data['terms']) && is_array($tax_data['terms']) && count($tax_data['terms']) > 0) {
                $filtered_original_taxonomies[$tax_key] = $tax_data;
            } else {
                error_log('HRW: Filtering out empty taxonomy: ' . $tax_key);
            }
        }
    }

    // Build final response - ONLY HRW restaurants with VibeMap matches
    $response = [
        'places'     => $merged_places,
        'categories' => $filtered_categories,
        'vibes'      => $filtered_vibes,
        'tags'       => $filtered_tags,
        'taxonomies' => array_merge($filtered_original_taxonomies, $used_hrw_taxonomies)
    ];

    // Add total count if requested
    if ($request->get_param('total_count')) {
        $response['total_count'] = count($response['places']);
        error_log('HRW: Added total_count: ' . $response['total_count']);
    }

    return $response;
}

/**
 * Get all custom taxonomies for HRW restaurants CPT
 */
function vibemap_hrw_get_custom_taxonomies()
{
    $taxonomies = [];

    // Get the configured taxonomy fields from settings
    $taxonomy_fields = get_option('vibemap_hrw_custom_taxonomies', '');
    $fields_array = array_map('trim', explode(',', $taxonomy_fields));

    // Get all HRW restaurants
    $hrw_restaurants = get_posts([
        'post_type' => 'hrw_restaurants',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ]);

    // Collect unique values for each configured field
    $field_values = [];
    foreach ($fields_array as $field_name) {
        if (!empty($field_name)) {
            $field_values[$field_name] = [];
        }
    }

    foreach ($hrw_restaurants as $restaurant) {
        foreach ($fields_array as $field_name) {
            if (empty($field_name)) {
                continue;
            }

            // Get field value
            $values = get_field($field_name, $restaurant->ID);
            if (!empty($values) && is_array($values)) {
                foreach ($values as $value) {
                    $name = is_array($value) ? ($value['name'] ?? $value[0] ?? '') : $value;
                    if (!empty($name) && !in_array($name, $field_values[$field_name])) {
                        $field_values[$field_name][] = $name;
                    }
                }
            }
        }
    }

    // Build taxonomies for each configured field
    foreach ($field_values as $field_name => $values) {
        if (empty($values)) {
            continue;
        }

        // Sort values alphabetically
        sort($values);

        // Build terms for this taxonomy
        $terms = [];
        foreach ($values as $value) {
            $slug = sanitize_title($value);
            $terms[] = [
                'id' => $field_name . '_' . $slug,
                'name' => $value,
                'slug' => $slug,
                'parent' => null,
                'icon' => 'tag' // Default icon
            ];
        }

        // Use the field name as is
        $taxonomy_key = $field_name;

        // Get configuration for this taxonomy
        $config = vibemap_hrw_get_taxonomy_config($taxonomy_key);

        // Use custom display name if set, otherwise generate from field name
        $display_name = !empty($config['display_name']) ? $config['display_name'] : ucwords(str_replace('_', ' ', $field_name));

        $taxonomies[$taxonomy_key] = [
            'name' => $display_name,
            'slug' => $taxonomy_key,
            'description' => $display_name . ' for HRW restaurants',
            'icon' => $config['icon'] ?? 'tag',
            'terms' => $terms
        ];
    }

    // Also check for any actual WordPress taxonomies associated with hrw_restaurants
    $hrw_taxonomies = get_object_taxonomies('hrw_restaurants', 'objects');

    foreach ($hrw_taxonomies as $taxonomy_slug => $taxonomy) {
        // Skip built-in taxonomies and ones we've already handled via settings
        $skip_taxonomies = array_merge(['category', 'post_tag'], $fields_array);
        if (in_array($taxonomy_slug, $skip_taxonomies)) {
            continue;
        }

        // Get all terms for this taxonomy
        $terms = get_terms([
            'taxonomy' => $taxonomy_slug,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            continue;
        }

        $formatted_terms = [];
        $config = vibemap_hrw_get_taxonomy_config($taxonomy_slug);
        foreach ($terms as $term) {
            $formatted_terms[] = [
                'id' => strval($term->term_id),
                'name' => $term->name,
                'slug' => $term->slug,
                'parent' => $term->parent ? strval($term->parent) : null,
                'icon' => $config['icon'],
            ];
        }

        // Add this taxonomy to our collection
        $taxonomies[$taxonomy_slug] = [
            'name' => $taxonomy->label,
            'slug' => $taxonomy_slug,
            'description' => $taxonomy->description,
            'hierarchical' => $taxonomy->hierarchical,
            'icon' => $config['icon'],
            'terms' => $formatted_terms
        ];
    }

    return $taxonomies;
}

/**
 * Get custom taxonomies for a specific HRW restaurant
 */
function vibemap_hrw_get_place_custom_taxonomies($post_id)
{
    $place_taxonomies = [];

    // Get the HRW restaurant data
    $hrw_restaurant = get_post($post_id);
    if (!$hrw_restaurant) {
        return $place_taxonomies;
    }

    // Get the configured taxonomy fields from settings
    $taxonomy_fields = get_option('vibemap_hrw_custom_taxonomies', '');
    $fields_array = array_map('trim', explode(',', $taxonomy_fields));

    // DEBUG: Log what fields we're processing
    error_log('HRW Custom Taxonomies DEBUG: Raw option value: "' . $taxonomy_fields . '"');
    error_log('HRW Custom Taxonomies DEBUG: Fields array: ' . json_encode($fields_array));
    error_log('HRW Custom Taxonomies DEBUG: Processing ' . count($fields_array) . ' fields for restaurant ID ' . $post_id);

    // Process each configured field
    foreach ($fields_array as $field_name) {
        if (empty($field_name)) {
            error_log('HRW Custom Taxonomies DEBUG: Skipping empty field name');
            continue;
        }

        error_log('HRW Custom Taxonomies DEBUG: Processing field "' . $field_name . '"');

        // Get the field value using ACF
        $field_value = get_field($field_name, $post_id);

        error_log('HRW Custom Taxonomies DEBUG: Field "' . $field_name . '" value: ' . json_encode($field_value));
        error_log('HRW Custom Taxonomies DEBUG: Field "' . $field_name . '" is_array: ' . (is_array($field_value) ? 'YES' : 'NO'));

        if (!empty($field_value) && is_array($field_value)) {
            $formatted_terms = [];

            foreach ($field_value as $term) {
                // Handle both string and array formats
                $name = is_array($term) ? ($term['name'] ?? $term[0] ?? '') : $term;
                if (!empty($name)) {
                    $slug = sanitize_title($name);
                    // Use the field name as is for the taxonomy key
                    $taxonomy_key = $field_name;

                    $config = vibemap_hrw_get_taxonomy_config($taxonomy_key);
                    // Get the taxonomy display name
                    $taxonomy_display_name = !empty($config['display_name']) ? $config['display_name'] : ucwords(str_replace('_', ' ', $field_name));

                    $formatted_terms[] = [
                        'id' => $field_name . '_' . $slug,
                        'name' => $name,
                        'slug' => $slug,
                        'parent' => null,
                        'icon' => $config['icon'],
                        // Added for frontend display - use this instead of taxonomy slug
                        'taxonomy_display_name' => $taxonomy_display_name,
                        'taxonomy_slug' => $field_name
                    ];
                }
            }

            if (!empty($formatted_terms)) {
                error_log('HRW Custom Taxonomies DEBUG: Added ' . count($formatted_terms) . ' terms for field "' . $field_name . '"');
                $place_taxonomies[$field_name] = $formatted_terms;
            }
        } else {
            error_log('HRW Custom Taxonomies DEBUG: Field "' . $field_name . '" - no valid array data found');
        }
    }

    // DEBUG: Log final results
    error_log('HRW Custom Taxonomies DEBUG: Final taxonomies for post ' . $post_id . ': ' . json_encode(array_keys($place_taxonomies)));

    // Also check for actual WordPress taxonomies
    $taxonomies = get_object_taxonomies('hrw_restaurants');

    foreach ($taxonomies as $taxonomy_slug) {
        // Skip built-in taxonomies and ones we've already handled via ACF
        $skip_taxonomies = array_merge(['category', 'post_tag'], $fields_array);
        if (in_array($taxonomy_slug, $skip_taxonomies)) {
            continue;
        }

        // Get terms for this post
        $terms = wp_get_post_terms($post_id, $taxonomy_slug, ['fields' => 'all']);

        if (!is_wp_error($terms) && !empty($terms)) {
            $formatted_terms = [];
            $config = vibemap_hrw_get_taxonomy_config($taxonomy_slug);
            // Get the taxonomy display name
            $taxonomy_display_name = !empty($config['display_name']) ? $config['display_name'] : ucwords(str_replace('_', ' ', $taxonomy_slug));

            foreach ($terms as $term) {
                $formatted_terms[] = [
                    'id' => strval($term->term_id),
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'parent' => $term->parent ? strval($term->parent) : null,
                    'icon' => $config['icon'],
                    'taxonomy_display_name' => $taxonomy_display_name,
                    'taxonomy_slug' => $taxonomy_slug
                ];
            }

            // Use taxonomy slug as-is
            $place_taxonomies[$taxonomy_slug] = $formatted_terms;
        }
    }

    return $place_taxonomies;
}

// Removed JavaScript override since transformData is not a window function

/**
 * Add HRW settings page as submenu of VibeMap
 */
function vibemap_hrw_add_settings_page()
{
    // Debug: Check if parent menu exists
    global $submenu;
    error_log('HRW Settings: Checking if vibemap menu exists...');

    if (!isset($GLOBALS['admin_page_hooks']['vibemap'])) {
        error_log('HRW Settings: ERROR - Parent menu "vibemap" not found! Available menus: ' . implode(', ', array_keys($GLOBALS['admin_page_hooks'] ?? [])));

        // Fallback: Add as top-level menu temporarily
        error_log('HRW Settings: Adding as top-level menu as fallback...');
        add_menu_page(
            'Houston Restaurant Week Settings',
            'HRW Settings',
            'manage_options',
            'vibemap-hrw-settings',
            'vibemap_hrw_render_settings_page',
            'dashicons-location-alt',
            90
        );
        return;
    }

    error_log('HRW Settings: Parent menu found, adding submenu...');

    $hook = add_submenu_page(
        'vibemap', // Parent slug
        'Houston Restaurant Week Settings', // Page title
        'HRW Settings', // Menu title
        'manage_options', // Capability
        'vibemap-hrw-settings', // Menu slug
        'vibemap_hrw_render_settings_page' // Callback function
    );

    error_log('HRW Settings: Submenu added with hook: ' . $hook);

    // Add direct access capability check
    add_action('admin_init', function () {
        if (isset($_GET['page']) && $_GET['page'] === 'vibemap-hrw-settings') {
            if (!current_user_can('manage_options')) {
                wp_die(__('Sorry, you are not allowed to access this page.', 'vibemap-hrw-helper'));
            }
        }
    });
}

/**
 * Register HRW settings
 */
function vibemap_hrw_register_settings()
{
    // Register the legacy string setting for backward compatibility
    register_setting('vibemap_hrw_settings_group', 'vibemap_hrw_custom_taxonomies', [
        'type' => 'string',
        'description' => 'Comma-separated list of custom taxonomies to extract from HRW data',
        'default' => ''
    ]);

    // Register the new JSON configuration setting
    register_setting('vibemap_hrw_settings_group', 'vibemap_hrw_taxonomy_config', [
        'type' => 'string',
        'description' => 'JSON configuration for custom taxonomies including icons',
        'default' => '{}'
    ]);

    // Register custom CSS setting
    register_setting('vibemap_hrw_settings_group', 'vibemap_hrw_custom_css', [
        'type' => 'string',
        'description' => 'Custom CSS overrides for VibeMap blocks',
        'default' => '',
        'sanitize_callback' => 'vibemap_hrw_sanitize_css'
    ]);

    // Register fallback image setting
    register_setting('vibemap_hrw_settings_group', 'vibemap_hrw_fallback_image', [
        'type' => 'string',
        'description' => 'Fallback image URL for restaurants without gallery images',
        'default' => '',
        'sanitize_callback' => 'esc_url_raw'
    ]);

    add_settings_section(
        'vibemap_hrw_taxonomies_section',
        __('Custom Taxonomies', 'vibemap-hrw-helper'),
        'vibemap_hrw_taxonomies_section_callback',
        'vibemap-hrw-settings'
    );

    add_settings_section(
        'vibemap_hrw_styling_section',
        __('Styling & CSS', 'vibemap-hrw-helper'),
        'vibemap_hrw_styling_section_callback',
        'vibemap-hrw-settings'
    );

    add_settings_field(
        'vibemap_hrw_custom_taxonomies',
        __('Custom Taxonomies to Extract', 'vibemap-hrw-helper'),
        'vibemap_hrw_custom_taxonomies_callback',
        'vibemap-hrw-settings',
        'vibemap_hrw_taxonomies_section'
    );

    add_settings_field(
        'vibemap_hrw_taxonomy_config',
        __('Taxonomy Display Names & Icons', 'vibemap-hrw-helper'),
        'vibemap_hrw_taxonomy_config_callback',
        'vibemap-hrw-settings',
        'vibemap_hrw_taxonomies_section'
    );

    add_settings_field(
        'vibemap_hrw_custom_css',
        __('Custom CSS', 'vibemap-hrw-helper'),
        'vibemap_hrw_custom_css_callback',
        'vibemap-hrw-settings',
        'vibemap_hrw_styling_section'
    );

    add_settings_field(
        'vibemap_hrw_fallback_image',
        __('Fallback Image URL', 'vibemap-hrw-helper'),
        'vibemap_hrw_fallback_image_callback',
        'vibemap-hrw-settings',
        'vibemap_hrw_styling_section'
    );
}

/**
 * Render the HRW settings page
 */
function vibemap_hrw_render_settings_page()
{
    // Add some custom CSS for the settings page
?>
    <style>
        .taxonomy-config-table {
            margin-top: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .taxonomy-config-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .taxonomy-config-row:last-child {
            border-bottom: none;
        }

        .taxonomy-config-row label {
            min-width: 120px;
            font-weight: 600;
        }

        .taxonomy-config-row select {
            width: 150px;
        }

        .taxonomy-config-row input[type="text"] {
            width: 200px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .taxonomy-config-row .icon-preview {
            font-size: 16px;
            margin-left: 10px;
            display: inline-block;
            width: 20px;
            height: 20px;
            vertical-align: middle;
        }

        .custom-css-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .custom-css-section textarea {
            font-family: Consolas, Monaco, 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
    </style>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('vibemap_hrw_settings_group');
            do_settings_sections('vibemap-hrw-settings');
            submit_button('Save Settings');
            ?>
        </form>

        <div class="card" style="margin-top: 20px;">
            <h2>Information</h2>
            <p>This plugin extends the main VibeMap plugin to support Houston Restaurant Week (HRW) data.</p>
            <h3>Custom Taxonomies</h3>
            <p>Enter a comma-separated list of taxonomy field names that should be extracted from the raw data and made available as filters.</p>
            <p><strong>Examples:</strong></p>
            <ul>
                <li><code>neighborhood,cuisine_types,menu_types</code> - Example for HRW restaurants</li>
                <li><code>size,price</code> - For other types of data</li>
                <li><code>mood,atmosphere,style</code> - For venue data</li>
            </ul>
            <p><strong>Note:</strong> The field names should match the property names in your raw data source.</p>

            <h3>Custom CSS</h3>
            <p>Add custom CSS to override VibeMap block styles. The CSS will be automatically added to pages that contain VibeMap blocks.</p>
            <p><strong>Benefits:</strong></p>
            <ul>
                <li>Only loads on pages with VibeMap blocks (no performance impact on other pages)</li>
                <li>Works in both frontend and Gutenberg editor</li>
                <li>CSS is sanitized for security</li>
            </ul>
            <p><strong>Direct URL:</strong> <code><?php echo admin_url('admin.php?page=vibemap-hrw-settings'); ?></code></p>
            <?php if (!isset($GLOBALS['admin_page_hooks']['vibemap'])): ?>
                <p style="color: #d63638;"><strong>Note:</strong> The HRW Settings menu is currently showing as a top-level menu because the main VibeMap menu was not found. This typically happens if the main VibeMap plugin is not active or loaded after this plugin.</p>
            <?php endif; ?>
        </div>
    </div>
<?php
}

/**
 * Section callback for taxonomies section
 */
function vibemap_hrw_taxonomies_section_callback()
{
    echo '<p>Configure which custom taxonomies should be extracted from the raw data and made available as filters.</p>';
}

/**
 * Field callback for custom taxonomies setting
 */
function vibemap_hrw_custom_taxonomies_callback()
{
    $value = get_option('vibemap_hrw_custom_taxonomies', '');
    echo '<input type="text" id="vibemap_hrw_custom_taxonomies" name="vibemap_hrw_custom_taxonomies" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">Enter comma-separated ACF field names exactly as they appear in ACF</p>';
}

/**
 * Field callback for taxonomy configuration setting
 */
function vibemap_hrw_taxonomy_config_callback()
{
    $taxonomies_list = get_option('vibemap_hrw_custom_taxonomies', '');
    $config_json = get_option('vibemap_hrw_taxonomy_config', '{}');
    $config = json_decode($config_json, true) ?: [];

    $taxonomies = array_map('trim', explode(',', $taxonomies_list));
    $available_icons = vibemap_hrw_get_available_icons();

    echo '<div class="taxonomy-config-table">';
    echo '<p style="margin-top: 0;"><strong>Configure display names and icons for each taxonomy:</strong></p>';

    foreach ($taxonomies as $taxonomy) {
        if (empty($taxonomy)) continue;

        $tax_config = isset($config[$taxonomy]) ? $config[$taxonomy] : ['icon' => 'tag', 'display_name' => ''];
        $selected_icon = isset($tax_config['icon']) ? $tax_config['icon'] : 'tag';
        $display_name = isset($tax_config['display_name']) ? $tax_config['display_name'] : ucwords(str_replace('_', ' ', $taxonomy));

        echo '<div class="taxonomy-config-row">';
        echo '<label style="min-width: 150px;"><strong>' . esc_html($taxonomy) . ':</strong></label>';

        // Display name input
        echo '<div style="margin-right: 15px;">';
        echo '<label style="font-weight: normal; margin-right: 5px;">Display Name:</label>';
        echo '<input type="text" class="taxonomy-display-name" data-taxonomy="' . esc_attr($taxonomy) . '" value="' . esc_attr($display_name) . '" placeholder="' . esc_attr(ucwords(str_replace('_', ' ', $taxonomy))) . '" />';
        echo '</div>';

        // Icon select
        echo '<div style="display: flex; align-items: center;">';
        echo '<label style="font-weight: normal; margin-right: 5px;">Icon:</label>';
        echo '<select class="taxonomy-icon-select" data-taxonomy="' . esc_attr($taxonomy) . '">';

        foreach ($available_icons as $icon_key => $icon_label) {
            $selected = ($selected_icon === $icon_key) ? 'selected' : '';
            echo '<option value="' . esc_attr($icon_key) . '" ' . $selected . '>' . esc_html($icon_label) . '</option>';
        }

        echo '</select>';
        echo '<span class="icon-preview" style="margin-left: 10px;">(' . esc_html($selected_icon) . ')</span>';
        echo '</div>';

        echo '</div>';
    }

    echo '</div>';
    echo '<input type="hidden" id="vibemap_hrw_taxonomy_config" name="vibemap_hrw_taxonomy_config" value="' . esc_attr($config_json) . '" />';

    // Add JavaScript to handle the configuration
?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function updateConfigField() {
                var config = {};
                $('.taxonomy-config-row').each(function() {
                    var $row = $(this);
                    var taxonomy = $row.find('.taxonomy-icon-select').data('taxonomy');
                    var icon = $row.find('.taxonomy-icon-select').val();
                    var displayName = $row.find('.taxonomy-display-name').val();

                    config[taxonomy] = {
                        icon: icon,
                        display_name: displayName
                    };
                });
                $('#vibemap_hrw_taxonomy_config').val(JSON.stringify(config));
            }

            // Update hidden field when icon changes
            $('.taxonomy-icon-select').on('change', function() {
                var selectedIcon = $(this).val();
                $(this).closest('.taxonomy-config-row').find('.icon-preview').text('(' + selectedIcon + ')');
                updateConfigField();
            });

            // Update hidden field when display name changes
            $('.taxonomy-display-name').on('input', function() {
                updateConfigField();
            });

            // Initialize on page load
            updateConfigField();
        });
    </script>
<?php
}

/**
 * Add HRW settings to the VibeMap settings API response
 */
function vibemap_hrw_add_settings_to_api($settings)
{
    // Add our custom taxonomies setting to the API response
    $settings['hrw_custom_taxonomies'] = get_option('vibemap_hrw_custom_taxonomies', '');

    // Add taxonomy configuration
    $config_json = get_option('vibemap_hrw_taxonomy_config', '{}');
    $settings['hrw_taxonomy_config'] = json_decode($config_json, true) ?: [];

    // Add fallback image URL
    $settings['hrw_fallback_image'] = get_option('vibemap_hrw_fallback_image', '');

    return $settings;
}

/**
 * Fix submenu file for correct highlighting
 */
function vibemap_hrw_fix_submenu_file($submenu_file)
{
    global $plugin_page;

    // Select the correct submenu item when on HRW settings page
    if ($plugin_page === 'vibemap-hrw-settings') {
        $submenu_file = 'vibemap-hrw-settings';
    }

    return $submenu_file;
}

/**
 * Section callback for styling section
 */
function vibemap_hrw_styling_section_callback()
{
    echo '<p>Add custom CSS to override VibeMap block styles. This CSS will be added to pages containing VibeMap blocks.</p>';
}

/**
 * Field callback for custom CSS setting
 */
function vibemap_hrw_custom_css_callback()
{
    $value = get_option('vibemap_hrw_custom_css', '');
    echo '<textarea id="vibemap_hrw_custom_css" name="vibemap_hrw_custom_css" rows="10" cols="80" class="large-text code">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Enter custom CSS to override VibeMap styles. Examples:<br>';
    echo '<code>.chip.selected { background: #84BD41 !important; color: #fff !important; }</code><br>';
    echo '<code>.filters { background-color: #f0f0f0; }</code><br>';
    echo '<code>.vibemap-card { border-radius: 10px; }</code><br>';
    echo '<strong>Note:</strong> Use <code>!important</code> if your styles are not taking precedence over existing styles.</p>';
}

/**
 * Sanitize CSS input
 */
function vibemap_hrw_sanitize_css($input)
{
    // Basic CSS sanitization - remove script tags and dangerous content
    $input = wp_strip_all_tags($input);
    $input = str_replace(['<script', '</script>', 'javascript:', 'expression(', 'eval('], '', $input);
    return $input;
}

/**
 * Field callback for fallback image setting
 */
function vibemap_hrw_fallback_image_callback()
{
    $value = get_option('vibemap_hrw_fallback_image', '');
    echo '<input type="url" id="vibemap_hrw_fallback_image" name="vibemap_hrw_fallback_image" value="' . esc_attr($value) . '" class="large-text" />';
    echo '<p class="description">Enter the URL of an image to use as a fallback when restaurants don\'t have images in the "photos_of_hrw_menu_items" gallery field.<br>';
    echo 'Example: <code>https://example.com/default-restaurant-image.jpg</code></p>';
}

/**
 * Output custom CSS on pages with VibeMap blocks
 */
function vibemap_hrw_output_custom_css()
{
    $custom_css = get_option('vibemap_hrw_custom_css', '');

    if (empty($custom_css)) {
        return;
    }

    // Check if we're on a page that might have VibeMap blocks
    if (vibemap_hrw_should_load_css()) {
        echo "\n<style id='vibemap-hrw-custom-css'>\n";
        echo "/* VibeMap HRW Custom CSS - Loaded on page with VibeMap blocks */\n";
        echo "/* To ensure styles apply, use specific selectors or !important */\n";
        echo $custom_css;
        echo "\n</style>\n";

        // Add a comment for debugging
        if (is_admin() || (defined('WP_DEBUG') && WP_DEBUG)) {
            echo "<!-- VibeMap HRW: Custom CSS loaded -->\n";
        }
    }
}

/**
 * Check if current page should have custom CSS loaded
 */
function vibemap_hrw_should_load_css()
{
    global $post;

    // Always load in admin (for Gutenberg editor)
    if (is_admin()) {
        return true;
    }

    // Load on single posts/pages that might contain blocks
    if (is_singular()) {
        if (!$post) {
            return false;
        }

        // Check if post content contains VibeMap blocks
        if (
            has_block('vibemap/places-map-native', $post) ||
            has_block('vibemap/native-events', $post) ||
            has_block('vibemap/card-carousel', $post) ||
            has_block('vibemap/single-card', $post) ||
            has_block('vibemap/bookmarks', $post) ||
            strpos($post->post_content, 'wp:vibemap/') !== false
        ) {
            return true;
        }
    }

    // For safety, also load if we detect VibeMap-related classes in the page
    // This is a fallback for cases where blocks might be loaded via shortcodes or other methods
    if (!is_admin()) {
        $page_content = get_the_content();
        if (
            strpos($page_content, 'vibemap') !== false ||
            strpos($page_content, 'wp-block-vibemap') !== false
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'vibemap_hrw_activate');
function vibemap_hrw_activate()
{
    // Check if main plugin is active
    if (!class_exists('VibeMap_REST_API')) {
        wp_die('This plugin requires the main VibeMap plugin to be installed and activated.');
    }
}
